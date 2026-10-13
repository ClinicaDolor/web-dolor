param(
    [int]$Port = 8081,
    [string]$Database = 'clinica_dolor_demo_20261010_final'
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$guardPath = Join-Path $root 'tests\p04\verify-isolation.php'
$routerPath = Join-Path $root 'tests\p04\router.php'

if ($Database -ne 'clinica_dolor_demo_20261010_final') {
    throw 'Fail-closed: sólo se permite la base sintética autorizada de P04.'
}

$env:P04_TEST_DATABASE = $Database
$guardJson = & php $guardPath "--database=$Database" '--json'
if ($LASTEXITCODE -ne 0) {
    throw 'La guarda de aislamiento rechazó el entorno; el servidor no se inició.'
}

$guard = ($guardJson -join "`n") | ConvertFrom-Json
if (-not $guard.ok -or $guard.database -ne $Database) {
    throw 'La guarda no confirmó la base sintética esperada; el servidor no se inició.'
}

$portInUse = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue
if ($portInUse) {
    throw "El puerto $Port ya está ocupado; el servidor no se inició para evitar ambigüedad."
}

$stdoutPath = Join-Path ([IO.Path]::GetTempPath()) "clinica-p04-$Port.stdout.log"
$stderrPath = Join-Path ([IO.Path]::GetTempPath()) "clinica-p04-$Port.stderr.log"
$appKey = [Guid]::NewGuid().ToString('N')
$server = Start-Process -FilePath 'php.exe' `
    -ArgumentList @('-d', 'variables_order=EGPCS', '-S', "127.0.0.1:$Port", $routerPath) `
    -WorkingDirectory $root `
    -Environment @{
        DB_DATABASE = $Database
        P04_TEST_DATABASE = $Database
        P04_TEST_MODE = '1'
        APP_ENV = 'testing'
        APP_KEY = $appKey
    } `
    -RedirectStandardOutput $stdoutPath `
    -RedirectStandardError $stderrPath `
    -WindowStyle Hidden `
    -PassThru

Start-Sleep -Milliseconds 350
if ($server.HasExited) {
    throw "El servidor terminó inmediatamente. Revisa los logs temporales: $stdoutPath y $stderrPath"
}

Write-Output "PID=$($server.Id)"
Write-Output "Base=$Database"
Write-Output "URL=http://127.0.0.1:$Port"
Write-Output "Stdout=$stdoutPath"
Write-Output "Stderr=$stderrPath"

