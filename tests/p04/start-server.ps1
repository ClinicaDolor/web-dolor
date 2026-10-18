param(
    [int]$Port = 8081,
    [string]$Database,
    [int]$PatientA,
    [int]$PatientB,
    [ValidateSet('intra-clinic', 'cross-clinic')]
    [string]$IsolationMode,
    [string]$OwnerAttestation
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$guardPath = Join-Path $root 'tests\p04\verify-isolation.php'
$routerPath = Join-Path $root 'tests\p04\router.php'

if ([string]::IsNullOrWhiteSpace($Database) -or $PatientA -lt 1 -or $PatientB -lt 1 -or $PatientA -eq $PatientB -or [string]::IsNullOrWhiteSpace($IsolationMode)) {
    throw 'Fail-closed: indique -Database, -PatientA, -PatientB y -IsolationMode explícitamente.'
}
if ($IsolationMode -eq 'intra-clinic' -and ($Database -ne 'bd_clinica' -or $OwnerAttestation -ne 'bd_clinica-ficticia-confirmada')) {
    throw 'Fail-closed: intra-clinic requiere bd_clinica y la atestación exacta del propietario.'
}
if ([string]::IsNullOrWhiteSpace($env:P04_TEST_APP_KEY) -or $env:P04_TEST_APP_KEY.Length -lt 32) {
    throw 'Fail-closed: defina P04_TEST_APP_KEY efímera en la sesión actual; no se genera ni se muestra una clave automáticamente.'
}

$guardJson = & php $guardPath "--database=$Database" "--patient-a=$PatientA" "--patient-b=$PatientB" "--isolation-mode=$IsolationMode" "--owner-attestation=$OwnerAttestation" '--json'
if ($LASTEXITCODE -ne 0) {
    throw 'La guarda de aislamiento rechazó el entorno; el servidor no se inició.'
}

$guard = ($guardJson -join "`n") | ConvertFrom-Json
if (-not $guard.ok -or $guard.database -ne $Database -or $guard.isolation_mode -ne $IsolationMode) {
    throw 'La guarda no confirmó la base y el modo solicitados; el servidor no se inició.'
}

$portInUse = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue
if ($portInUse) {
    throw "El puerto $Port ya está ocupado; el servidor no se inició para evitar ambigüedad."
}

$stdoutPath = Join-Path ([IO.Path]::GetTempPath()) "clinica-p04-$Port.stdout.log"
$stderrPath = Join-Path ([IO.Path]::GetTempPath()) "clinica-p04-$Port.stderr.log"
$server = $null
$startFailure = $null
$environmentKeys = @(
    'DB_DATABASE',
    'P04_TEST_DATABASE',
    'P04_TEST_PATIENT_A',
    'P04_TEST_PATIENT_B',
    'P04_TEST_ISOLATION_MODE',
    'P04_SYNTHETIC_OWNER_ATTESTATION',
    'P04_TEST_APP_KEY',
    'P04_TEST_RUN_ID',
    'P04_TEST_MODE',
    'APP_ENV',
    'APP_KEY'
)
$hadEnvironmentValue = @{}
$originalEnvironmentValue = @{}

foreach ($key in $environmentKeys) {
    $environmentVariable = Get-Item -Path ("Env:{0}" -f $key) -ErrorAction SilentlyContinue
    $hadEnvironmentValue[$key] = $null -ne $environmentVariable
    if ($hadEnvironmentValue[$key]) {
        $originalEnvironmentValue[$key] = [string]$environmentVariable.Value
    }
}

try {
    # PowerShell 5.1 no admite la opción Environment de Start-Process. Las variables se
    # preparan sólo en este proceso, se heredan al hijo y se restauran debajo.
    $env:DB_DATABASE = $Database
    $env:P04_TEST_DATABASE = $Database
    $env:P04_TEST_PATIENT_A = [string]$PatientA
    $env:P04_TEST_PATIENT_B = [string]$PatientB
    $env:P04_TEST_ISOLATION_MODE = $IsolationMode
    $env:P04_SYNTHETIC_OWNER_ATTESTATION = $OwnerAttestation
    $env:P04_TEST_MODE = '1'
    $env:APP_ENV = 'testing'
    $env:APP_KEY = $env:P04_TEST_APP_KEY

    $server = Start-Process -FilePath 'php.exe' `
        -ArgumentList @('-d', 'variables_order=EGPCS', '-S', "127.0.0.1:$Port", $routerPath) `
        -WorkingDirectory $root `
        -RedirectStandardOutput $stdoutPath `
        -RedirectStandardError $stderrPath `
        -WindowStyle Hidden `
        -PassThru `
        -ErrorAction Stop
} catch {
    $startFailure = $_
} finally {
    foreach ($key in $environmentKeys) {
        $environmentPath = "Env:{0}" -f $key
        if ($hadEnvironmentValue[$key]) {
            Set-Item -Path $environmentPath -Value $originalEnvironmentValue[$key]
        } else {
            Remove-Item -Path $environmentPath -ErrorAction SilentlyContinue
        }
    }
}

if ($null -ne $startFailure) {
    throw 'No fue posible iniciar el servidor PHP temporal; no se expusieron detalles de entorno.'
}
if ($null -eq $server -or $server.HasExited) {
    throw "El servidor terminó inmediatamente. Revisa los logs temporales: $stdoutPath y $stderrPath"
}

Write-Output "PID=$($server.Id)"
Write-Output "Base=$Database"
Write-Output "IsolationMode=$IsolationMode"
Write-Output "PatientA=$PatientA"
Write-Output "PatientB=$PatientB"
Write-Output "URL=http://127.0.0.1:$Port"
Write-Output "Stdout=$stdoutPath"
Write-Output "Stderr=$stderrPath"
