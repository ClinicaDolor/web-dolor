[CmdletBinding()]
param(
    [string]$PhpPath = 'C:\xampp\php\php.exe',
    [string]$BaselinePath = 'C:\Users\silvi\AppData\Local\ClinicaDolor\M07-baselines\m07-clinical-baseline-20261015T152934Z-7701ca5e.json',
    [switch]$AllowSyntheticCredentialWrites,
    [string]$SyntheticConfirmation = '',
    [int]$ApacheStartTimeoutSeconds = 20,
    [int]$PhpTimeoutSeconds = 30,
    [int]$HttpTimeoutSeconds = 15
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$database = 'clinica_dolor_demo_20261010_final'
$patientA = 9301
$patientB = 9302
$port = 8117
$runtimeBase = Join-Path ([IO.Path]::GetTempPath()) 'clinica-m07-http-isolated'
$approvedBaselinePath = 'C:\Users\silvi\AppData\Local\ClinicaDolor\M07-baselines\m07-clinical-baseline-20261015T152934Z-7701ca5e.json'
$startScript = Join-Path $PSScriptRoot 'start-m07-apache.ps1'
$stopScript = Join-Path $PSScriptRoot 'stop-m07-apache.ps1'
$fixtureHelper = Join-Path $root 'tests\patient-interview\m07-p04-medium-fixture.php'
$p04Verifier = Join-Path $root 'tests\p04\verify-isolation.php'
$preflightScript = Join-Path $root 'tests\patient-interview\m07-shared-canonical-validate-existing.php'
$baselineScript = Join-Path $root 'tests\patient-interview\m07-durable-clinical-baseline.php'
$matrixScript = Join-Path $PSScriptRoot 'm07-b1-http-readonly-matrix.php'
$expectedApachePath = [IO.Path]::GetFullPath('C:\xampp\apache\bin\httpd.exe')
$credentialSnapshot = Join-Path ([IO.Path]::GetTempPath()) ('m07-b1-http-access-' + [Guid]::NewGuid().ToString('N') + '.snapshot.json')
$preflightRuntime = $null
$normalRuntime = $null
$orphanRuntime = $null
$credentialSnapshotCreated = $false
$restored = $false
$baselineBefore = $null
$baselineAfter = $null
$p04 = $null
$preflight = $null
$preflightStatus = 'NOT_REACHED'
$preflightExitCode = 'NOT_REACHED'
$preflightFirstFailedStep = 'NOT_REACHED'
$preflightError = 'NOT_REACHED'
$matrix = $null
$failure = $null
$cleanupFailure = $false
$cleanupFailureReason = $null
$integrityFailure = $false
$integrityFailureReason = $null
$matrixInvoked = $false
$doctorLoginA = 'NOT_REACHED'
$doctorLoginB = 'NOT_REACHED'
$matrixExitCode = 'NOT_REACHED'
$apacheStartAttempted = 'NO'
$apacheStartResult = 'NOT_REACHED'
$preflightRuntimeRecovered = 'NO'
$preflightHttpAttempted = 'NO'
$preflightHttpStatus = 'NOT_REACHED'
$preflightHttpBodyClass = 'NOT_REACHED'
$preflightHttpResult = 'NOT_REACHED'
$currentStage = 'initialization'
$lastCompletedCheckpoint = 'initialization'
$lastProcessId = 'NOT_REPORTED'
$lastProcessKind = 'NOT_REPORTED'
$lastProcessStage = 'NOT_REPORTED'
$failureStage = 'NOT_REACHED'
$failureProcessId = 'NOT_REPORTED'
$failureProcessKind = 'NOT_REPORTED'
$failureOperation = 'NOT_REPORTED'
$oldPinA = [Environment]::GetEnvironmentVariable('P04_TEST_PIN_A', 'Process')
$oldPinB = [Environment]::GetEnvironmentVariable('P04_TEST_PIN_B', 'Process')
$oldDoctorA = [Environment]::GetEnvironmentVariable('M07_B1_DOCTOR_TOKEN_A', 'Process')
$oldDoctorB = [Environment]::GetEnvironmentVariable('M07_B1_DOCTOR_TOKEN_B', 'Process')

function Assert-File([string]$Path, [string]$Message) {
    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) { throw $Message }
}

function Set-RunnerStage([string]$Stage) {
    $script:currentStage = $Stage
}

function Complete-RunnerCheckpoint([string]$Checkpoint) {
    $script:lastCompletedCheckpoint = $Checkpoint
}

function Assert-MediumIntegrity {
    $groups = (& whoami.exe /groups /fo csv /nh 2>$null | Out-String)
    if ($groups -notmatch '(?i)Medium Mandatory Level') {
        throw 'El ejecutor debe iniciarse desde PowerShell normal con integridad Medium.'
    }
}

function ConvertTo-PowerShellLiteral([string]$Value) {
    return ("'" + $Value.Replace("'", "''") + "'")
}

function Get-CapturedRuntime([string]$OutputPath) {
    if (-not (Test-Path -LiteralPath $OutputPath -PathType Leaf)) { return $null }
    $baseFull = ([IO.Path]::GetFullPath($runtimeBase)).TrimEnd('\') + '\'
    foreach ($line in Get-Content -LiteralPath $OutputPath -ErrorAction SilentlyContinue) {
        if ($line -match '^RUNTIME=(.+)$') {
            try { $candidate = [IO.Path]::GetFullPath($Matches[1].Trim()) } catch { continue }
            if ($candidate.StartsWith($baseFull, [StringComparison]::OrdinalIgnoreCase)) { return $candidate }
        }
    }
    return $null
}

function Find-NewRuntime([bool]$ExpectPreflightOnly, [hashtable]$KnownRuntimes) {
    if (-not (Test-Path -LiteralPath $runtimeBase -PathType Container)) { return $null }
    $baseFull = ([IO.Path]::GetFullPath($runtimeBase)).TrimEnd('\') + '\'
    foreach ($directory in (Get-ChildItem -LiteralPath $runtimeBase -Directory | Sort-Object LastWriteTime -Descending)) {
        if ($KnownRuntimes.ContainsKey($directory.Name)) { continue }
        $statePath = Join-Path $directory.FullName 'process.json'
        if (-not (Test-Path -LiteralPath $statePath -PathType Leaf)) { continue }
        try {
            $state = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
            if ([string]$state.database -eq $database -and [int]$state.port -eq $port -and
                [bool]$state.preflightOnly -eq $ExpectPreflightOnly -and
                [string]$state.runtime -eq $directory.FullName -and
                $directory.FullName.StartsWith($baseFull, [StringComparison]::OrdinalIgnoreCase)) { return $directory.FullName }
        } catch { }
    }
    return $null
}

function Invoke-JsonPhp {
    param([string]$ScriptPath, [string[]]$Arguments)
    $stdoutPath = [IO.Path]::GetTempFileName()
    $stderrPath = Join-Path ([IO.Path]::GetTempPath()) ('m07-b1-php-stderr-' + [Guid]::NewGuid().ToString('N') + '.log')
    $statusPath = Join-Path ([IO.Path]::GetTempPath()) ('m07-b1-php-status-' + [Guid]::NewGuid().ToString('N') + '.json')
    $wrapperPath = Join-Path ([IO.Path]::GetTempPath()) ('m07-b1-php-' + [Guid]::NewGuid().ToString('N') + '.ps1')
    $process = $null
    try {
        $invocationArguments = @($PhpPath, $ScriptPath) + $Arguments
        $invocation = '& ' + (($invocationArguments | ForEach-Object { ConvertTo-PowerShellLiteral ([string]$_) }) -join ' ')
        $wrapperLines = @(
            '$ErrorActionPreference = ''Stop'''
            'try {'
            ('    ' + $invocation)
            ('    [ordered]@{ completed = $true; exit_code = [int]$LASTEXITCODE } | ConvertTo-Json -Compress | Set-Content -LiteralPath ' + (ConvertTo-PowerShellLiteral $statusPath) + ' -Encoding UTF8')
            '    exit 0'
            '} catch {'
            ('    [ordered]@{ completed = $false; exit_code = 1 } | ConvertTo-Json -Compress | Set-Content -LiteralPath ' + (ConvertTo-PowerShellLiteral $statusPath) + ' -Encoding UTF8')
            '    exit 1'
            '}'
        )
        Set-Content -LiteralPath $wrapperPath -Value ($wrapperLines -join [Environment]::NewLine) -Encoding UTF8
        $process = Start-Process powershell.exe -ArgumentList @('-NoLogo','-NoProfile','-ExecutionPolicy','Bypass','-File',$wrapperPath) -WorkingDirectory $root -WindowStyle Hidden -RedirectStandardOutput $stdoutPath -RedirectStandardError $stderrPath -PassThru
        $script:lastProcessId = [string]$process.Id
        $script:lastProcessKind = 'PHP_WRAPPER'
        $script:lastProcessStage = $script:currentStage
        if (-not $process.WaitForExit($PhpTimeoutSeconds * 1000)) {
            & taskkill.exe /PID $process.Id /T /F >$null 2>$null
            throw 'El proceso PHP auxiliar excedió su timeout controlado.'
        }
        if (-not (Test-Path -LiteralPath $statusPath -PathType Leaf)) { throw 'El proceso PHP terminó sin resultado explícito.' }
        $status = Get-Content -LiteralPath $statusPath -Raw | ConvertFrom-Json
        if (-not [bool]$status.completed) { throw 'El proceso PHP terminó con error controlado.' }
        $exitCode = [int]$status.exit_code
        $text = (Get-Content -LiteralPath $stdoutPath -Raw -ErrorAction SilentlyContinue).Trim()
        if ($text -eq '') { throw "El proceso PHP no devolvió JSON controlado. Código: $exitCode" }
        try { $json = $text | ConvertFrom-Json } catch { throw "El proceso PHP devolvió JSON inválido. Código: $exitCode" }
        return [PSCustomObject]@{ ExitCode = $exitCode; Json = $json }
    } finally {
        if ($null -ne $process) { try { $process.Refresh() } catch { }; if (-not $process.HasExited) { & taskkill.exe /PID $process.Id /T /F >$null 2>$null } }
        Remove-Item -LiteralPath $stdoutPath,$stderrPath,$statusPath,$wrapperPath -Force -ErrorAction SilentlyContinue
    }
}

function Start-ApacheDetached([bool]$PreflightOnly, [hashtable]$KnownRuntimes) {
    $outputPath = [IO.Path]::GetTempFileName()
    $errorPath = Join-Path ([IO.Path]::GetTempPath()) ('m07-b1-apache-stderr-' + [Guid]::NewGuid().ToString('N') + '.log')
    $statusPath = Join-Path ([IO.Path]::GetTempPath()) ('m07-b1-apache-status-' + [Guid]::NewGuid().ToString('N') + '.json')
    $wrapperPath = Join-Path ([IO.Path]::GetTempPath()) ('m07-b1-apache-' + [Guid]::NewGuid().ToString('N') + '.ps1')
    $launcher = $null
    $runtime = $null
    try {
        $preflightArgument = if ($PreflightOnly) { ' -PreflightOnly' } else { '' }
        $wrapperLines = @(
            '$ErrorActionPreference = ''Stop'''
            'try {'
            ('    & ' + (ConvertTo-PowerShellLiteral $startScript) + ' -Port ' + [string]$port + $preflightArgument)
            ('    [ordered]@{ completed = $true; status = ''START_COMPLETED'' } | ConvertTo-Json -Compress | Set-Content -LiteralPath ' + (ConvertTo-PowerShellLiteral $statusPath) + ' -Encoding UTF8')
            '    exit 0'
            '} catch {'
            ('    [ordered]@{ completed = $false; status = ''START_FAILED'' } | ConvertTo-Json -Compress | Set-Content -LiteralPath ' + (ConvertTo-PowerShellLiteral $statusPath) + ' -Encoding UTF8')
            '    exit 1'
            '}'
        )
        Set-Content -LiteralPath $wrapperPath -Value ($wrapperLines -join [Environment]::NewLine) -Encoding UTF8
        $launcher = Start-Process powershell.exe -ArgumentList @('-NoLogo','-NoProfile','-ExecutionPolicy','Bypass','-File',$wrapperPath) -WorkingDirectory $root -WindowStyle Hidden -RedirectStandardOutput $outputPath -RedirectStandardError $errorPath -PassThru
        $script:lastProcessId = [string]$launcher.Id
        $script:lastProcessKind = 'APACHE_LAUNCHER_WRAPPER'
        $script:lastProcessStage = $script:currentStage
        if (-not $launcher.WaitForExit($ApacheStartTimeoutSeconds * 1000)) {
            & taskkill.exe /PID $launcher.Id /T /F >$null 2>$null
            $runtime = Find-NewRuntime $PreflightOnly $KnownRuntimes
            if ($null -ne $runtime) { $script:orphanRuntime = $runtime }
            throw 'El iniciador Apache excedió su timeout controlado.'
        }
        if (-not (Test-Path -LiteralPath $statusPath -PathType Leaf)) { throw 'El iniciador Apache terminó sin resultado explícito.' }
        $status = Get-Content -LiteralPath $statusPath -Raw | ConvertFrom-Json
        if (-not [bool]$status.completed -or [string]$status.status -ne 'START_COMPLETED') { throw 'El iniciador Apache no reportó START_COMPLETED.' }
        $runtime = Get-CapturedRuntime $outputPath
        if ($null -eq $runtime) { $runtime = Find-NewRuntime $PreflightOnly $KnownRuntimes }
        if ($null -eq $runtime) { throw 'No se pudo recuperar RUNTIME= de Apache sintético.' }
        return $runtime
    } catch {
        if ($null -eq $script:orphanRuntime -and $null -ne $runtime) { $script:orphanRuntime = $runtime }
        throw
    } finally {
        if ($null -ne $launcher) { try { $launcher.Refresh() } catch { }; if (-not $launcher.HasExited) { & taskkill.exe /PID $launcher.Id /T /F >$null 2>$null } }
        Remove-Item -LiteralPath $outputPath,$errorPath,$statusPath,$wrapperPath -Force -ErrorAction SilentlyContinue
    }
}

function Assert-ApacheRuntime([string]$Runtime, [bool]$ExpectPreflightOnly) {
    $statePath = Join-Path $Runtime 'process.json'
    Assert-File $statePath 'No existe process.json del Apache sintético.'
    $state = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
    if ([string]$state.database -ne $database -or [int]$state.port -ne $port -or [bool]$state.preflightOnly -ne $ExpectPreflightOnly) { throw 'El runtime Apache no corresponde al modo sintético esperado.' }
    if ([IO.Path]::GetFullPath([string]$state.executable) -ne $expectedApachePath -or [int]$state.controlPid -ne [int]$state.pid) { throw 'El proceso Apache sintético no es verificable.' }
    if (-not (Test-Path -LiteralPath ([string]$state.config) -PathType Leaf)) { throw 'La configuración Apache sintética no existe.' }
    $process = Get-Process -Id ([int]$state.pid) -ErrorAction SilentlyContinue
    if ($null -eq $process -or [IO.Path]::GetFullPath([string]$process.Path) -ne $expectedApachePath) { throw 'El PID Apache no corresponde al ejecutable registrado.' }
    $listener = Get-NetTCPConnection -State Listen -LocalAddress '127.0.0.1' -LocalPort $port -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($null -eq $listener -or [int]$listener.OwningProcess -ne [int]$state.listenerPid) { throw 'El listener no corresponde al runtime registrado.' }
}

function Assert-PermanentGuard([bool]$PreflightOnly) {
    if ($PreflightOnly) { $script:preflightHttpAttempted = 'YES' }
    $request = [System.Net.HttpWebRequest]::Create('http://127.0.0.1:' + $port + '/historia-clinica/acceso')
    $request.Method = 'GET'; $request.Timeout = $HttpTimeoutSeconds * 1000; $request.ReadWriteTimeout = $HttpTimeoutSeconds * 1000
    $response = $null; $reader = $null
    try {
        try { $response = [System.Net.HttpWebResponse]$request.GetResponse() } catch [System.Net.WebException] { $response = $_.Exception.Response; if ($null -eq $response) { throw 'El guard permanente no respondió.' } }
        $statusCode = [int]$response.StatusCode
        $databaseGuard = [string]$response.Headers['X-M07-Database-Guard']
        $body = ''
        try { $reader = New-Object System.IO.StreamReader($response.GetResponseStream()); $body = $reader.ReadToEnd() } finally { if ($null -ne $reader) { $reader.Dispose() }; $response.Dispose() }
        if ($PreflightOnly) {
            $script:preflightHttpStatus = [string]$statusCode
            $trimmedBody = $body.Trim()
            $script:preflightHttpBodyClass = if ($trimmedBody -eq 'PASS') { 'PASS' } elseif ($trimmedBody -eq 'FAIL') { 'FAIL' } elseif ($trimmedBody -eq '') { 'EMPTY' } else { 'OTHER' }
            $script:preflightHttpResult = if ($statusCode -eq 200 -and $script:preflightHttpBodyClass -eq 'PASS') { 'PASS' } else { 'FAIL' }
        }
        if ($statusCode -ne 200) { throw 'El guard permanente no devolvió HTTP 200.' }
        if ($PreflightOnly -and $body.Trim() -ne 'PASS') { throw 'El preflight HTTP no devolvió exactamente PASS.' }
        if (-not $PreflightOnly -and $databaseGuard -ne 'PASS') { throw 'La petición normal no confirmó el guard permanente.' }
    } catch { throw $_.Exception.Message }
}

function Invoke-BaselineCompare { return Invoke-JsonPhp $baselineScript @('--mode=compare',"--database=$database","--patient-a=$patientA","--patient-b=$patientB","--snapshot=$BaselinePath") }

function ConvertFrom-SecureStringForRequest([Security.SecureString]$SecurePassword) { $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($SecurePassword); try { return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer) } finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer) } }

function Invoke-DoctorLogin([string]$Username, [string]$Password) {
    $body = @{ usuario = $Username; password = $Password } | ConvertTo-Json -Compress
    try { $response = Invoke-WebRequest -Uri ('http://127.0.0.1:' + $port + '/clinica/login') -Method Post -ContentType 'application/json' -Body $body -UseBasicParsing -TimeoutSec $HttpTimeoutSeconds -ErrorAction Stop } catch { throw 'El login Doctor sintético no pudo completarse mediante /clinica/login.' }
    try { $payload = $response.Content | ConvertFrom-Json } catch { throw 'El login Doctor sintético no devolvió JSON válido.' }
    $token = [string]$payload.token
    if ([int]$response.StatusCode -ne 200 -or $payload.resultado -ne $true -or [string]::IsNullOrWhiteSpace($token)) { throw 'La credencial Doctor sintética fue rechazada por el login real.' }
    return $token
}

Assert-MediumIntegrity
if ([IO.Path]::GetFullPath($BaselinePath) -ne [IO.Path]::GetFullPath($approvedBaselinePath)) { throw 'Fail-closed: sólo se admite la línea base M07 aprobada.' }
foreach ($path in @($PhpPath,$startScript,$stopScript,$fixtureHelper,$p04Verifier,$preflightScript,$baselineScript,$matrixScript,$BaselinePath)) { Assert-File $path "Falta un componente requerido: $path" }
if (-not $AllowSyntheticCredentialWrites -or $SyntheticConfirmation -ne 'I_CONFIRM_M07_B1_HTTP_SYNTHETIC_9301_9302') { throw 'La ejecución exige autorización explícita para credenciales sintéticas.' }
$knownRuntimes = @{}

try {
    Set-RunnerStage 'apache_preflight_start'
    $preflightRuntime = Start-ApacheDetached $true $knownRuntimes
    $apacheStartAttempted = 'YES'
    $apacheStartResult = 'START_COMPLETED'
    $preflightRuntimeRecovered = 'YES'
    $knownRuntimes[(Split-Path -Leaf $preflightRuntime)] = $true
    Complete-RunnerCheckpoint 'apache_preflight_started'
    Set-RunnerStage 'apache_preflight_runtime_validation'
    Assert-ApacheRuntime $preflightRuntime $true
    Complete-RunnerCheckpoint 'apache_preflight_runtime_validated'
    Set-RunnerStage 'apache_preflight_http_guard'
    Assert-PermanentGuard $true
    Complete-RunnerCheckpoint 'apache_preflight_http_guard_passed'
    Set-RunnerStage 'baseline_before'
    $baselineBefore = Invoke-BaselineCompare
    if ($baselineBefore.ExitCode -ne 0 -or [string]$baselineBefore.Json.status -ne 'PASS_BASELINE_MATCH') { throw 'La línea base M07 no coincide antes de preparar la aceptación HTTP.' }
    Complete-RunnerCheckpoint 'baseline_before_passed'
    Set-RunnerStage 'credential_preflight'
    $fixturePreflight = Invoke-JsonPhp $fixtureHelper @('--action=preflight',"--root=$root","--database=$database","--snapshot=$credentialSnapshot")
    if ($fixturePreflight.ExitCode -ne 0 -or -not [bool]$fixturePreflight.Json.ok) { throw 'El preflight de credenciales P04 no pasó.' }
    Complete-RunnerCheckpoint 'credential_preflight_passed'
    Set-RunnerStage 'credential_snapshot'
    $prepare = Invoke-JsonPhp $fixtureHelper @('--action=prepare',"--root=$root","--database=$database","--snapshot=$credentialSnapshot")
    if ($prepare.ExitCode -ne 0 -or -not [bool]$prepare.Json.ok -or [string]$prepare.Json.database_effective -ne $database) { throw 'No se pudo crear el snapshot temporal de credenciales.' }
    $credentialSnapshotCreated = Test-Path -LiteralPath $credentialSnapshot -PathType Leaf
    if (-not $credentialSnapshotCreated) { throw 'El snapshot temporal de credenciales no quedó verificable.' }
    Complete-RunnerCheckpoint 'credential_snapshot_created'
    Set-RunnerStage 'preflight_apache_stop'
    $lastProcessId = 'NOT_REPORTED'
    $preflightStatePath = Join-Path $preflightRuntime 'process.json'
    if (Test-Path -LiteralPath $preflightStatePath -PathType Leaf) {
        try { $lastProcessId = [string]((Get-Content -LiteralPath $preflightStatePath -Raw | ConvertFrom-Json).pid) } catch { $lastProcessId = 'NOT_REPORTED' }
    }
    $lastProcessKind = 'APACHE_STOP_HELPER'
    $lastProcessStage = $currentStage
    & $stopScript -Runtime $preflightRuntime | Out-Null
    $preflightRuntime = $null
    Complete-RunnerCheckpoint 'apache_preflight_stopped'
    Set-RunnerStage 'credential_create'
    $create = Invoke-JsonPhp $fixtureHelper @('--action=create',"--root=$root","--database=$database","--snapshot=$credentialSnapshot")
    if ($create.ExitCode -ne 0 -or -not [bool]$create.Json.ok -or -not [bool]$create.Json.independent_active_credentials) { throw 'No se pudieron preparar credenciales de paciente independientes.' }
    Complete-RunnerCheckpoint 'credentials_created'
    Set-RunnerStage 'p04_guard'
    $p04 = Invoke-JsonPhp $p04Verifier @("--database=$database","--patient-a=$patientA","--patient-b=$patientB",'--isolation-mode=cross-clinic','--json')
    if ($p04.ExitCode -ne 0 -or -not [bool]$p04.Json.ok) { throw 'P04 no pasó durante la ventana controlada.' }
    Complete-RunnerCheckpoint 'p04_passed'
    Set-RunnerStage 'b1_read_only_preflight'
    $preflight = Invoke-JsonPhp $preflightScript @('--mode=preflight',"--database=$database","--patient-a=$patientA","--patient-b=$patientB")
    $preflightExitCode = [string]$preflight.ExitCode
    $preflightStatus = [string]$preflight.Json.status
    $preflightFirstFailedStep = if ([string]::IsNullOrWhiteSpace([string]$preflight.Json.first_failed_step)) { 'NONE' } else { [string]$preflight.Json.first_failed_step }
    $preflightError = if ([string]::IsNullOrWhiteSpace([string]$preflight.Json.error_message_sanitized)) { 'NONE' } else { ([string]$preflight.Json.error_message_sanitized -replace '[\r\n]+',' ').Trim() }
    if ($preflight.ExitCode -ne 0 -or $preflightStatus -ne 'PASS_READ_ONLY_EXISTING_SCHEMA') { throw ('El preflight B1 read-only no pasó. Estado=' + $preflightStatus + '; código=' + $preflightExitCode + '; etapa=' + $preflightFirstFailedStep + '; error=' + $preflightError) }
    Complete-RunnerCheckpoint 'b1_read_only_preflight_passed'
    Set-RunnerStage 'apache_normal_start'
    $normalRuntime = Start-ApacheDetached $false $knownRuntimes
    $knownRuntimes[(Split-Path -Leaf $normalRuntime)] = $true
    Assert-ApacheRuntime $normalRuntime $false
    Assert-PermanentGuard $false
    Complete-RunnerCheckpoint 'apache_normal_guard_passed'

    Set-RunnerStage 'doctor_login_a'
    $userA = Read-Host 'Usuario Doctor sintético 9201 (no se registrará)'
    $secureA = Read-Host 'Contraseña Doctor sintético 9201 (no se registrará)' -AsSecureString
    $passwordA = ConvertFrom-SecureStringForRequest $secureA
    $secureA = $null
    $tokenA = Invoke-DoctorLogin $userA $passwordA
    $passwordA = $null; $userA = $null
    $doctorLoginA = 'PASS_SAME_INSTANCE'
    Complete-RunnerCheckpoint 'doctor_login_a_passed'
    Set-RunnerStage 'doctor_login_b'
    $userB = Read-Host 'Usuario Doctor sintético 9202 (no se registrará)'
    $secureB = Read-Host 'Contraseña Doctor sintético 9202 (no se registrará)' -AsSecureString
    $passwordB = ConvertFrom-SecureStringForRequest $secureB
    $secureB = $null
    $tokenB = Invoke-DoctorLogin $userB $passwordB
    $passwordB = $null; $userB = $null
    $doctorLoginB = 'PASS_SAME_INSTANCE'
    Complete-RunnerCheckpoint 'doctor_login_b_passed'
    [Environment]::SetEnvironmentVariable('M07_B1_DOCTOR_TOKEN_A', $tokenA, 'Process')
    [Environment]::SetEnvironmentVariable('M07_B1_DOCTOR_TOKEN_B', $tokenB, 'Process')
    [Environment]::SetEnvironmentVariable('P04_TEST_PIN_A', 'cdp' + [string]$patientA, 'Process')
    [Environment]::SetEnvironmentVariable('P04_TEST_PIN_B', 'cdp' + [string]$patientB, 'Process')
    $matrixInvoked = $true
    Set-RunnerStage 'http_matrix'
    $matrix = Invoke-JsonPhp $matrixScript @("--database=$database",'--base-url=http://127.0.0.1:8117',"--patient-a=$patientA","--patient-b=$patientB")
    $matrixExitCode = [string]$matrix.ExitCode
    if ($matrix.ExitCode -ne 0 -or -not [bool]$matrix.Json.ok) { throw 'La matriz B1 de lectura HTTP no pasó completamente.' }
    $baselineAfter = Invoke-BaselineCompare
    if ($baselineAfter.ExitCode -ne 0 -or [string]$baselineAfter.Json.status -ne 'PASS_BASELINE_MATCH') { throw 'La línea base no coincide después de la matriz B1.' }
    Complete-RunnerCheckpoint 'http_matrix_and_baseline_passed'
} catch {
    $failure = (($_.Exception.Message -replace '[\r\n]+',' ').Trim())
    $failureStage = $currentStage
    $failureProcessId = $lastProcessId
    $failureProcessKind = $lastProcessKind
    $failureOperation = $lastProcessStage
} finally {
    [Environment]::SetEnvironmentVariable('P04_TEST_PIN_A', $oldPinA, 'Process')
    [Environment]::SetEnvironmentVariable('P04_TEST_PIN_B', $oldPinB, 'Process')
    [Environment]::SetEnvironmentVariable('M07_B1_DOCTOR_TOKEN_A', $oldDoctorA, 'Process')
    [Environment]::SetEnvironmentVariable('M07_B1_DOCTOR_TOKEN_B', $oldDoctorB, 'Process')
    if ($null -ne $normalRuntime) { try { & $stopScript -Runtime $normalRuntime | Out-Null } catch { $cleanupFailure = $true; if ($null -eq $cleanupFailureReason) { $cleanupFailureReason = (($_.Exception.Message -replace '[\r\n]+',' ').Trim()) } } }
    if ($null -ne $preflightRuntime) { try { & $stopScript -Runtime $preflightRuntime | Out-Null } catch { $cleanupFailure = $true; if ($null -eq $cleanupFailureReason) { $cleanupFailureReason = (($_.Exception.Message -replace '[\r\n]+',' ').Trim()) } } }
    if ($null -ne $orphanRuntime) { try { & $stopScript -Runtime $orphanRuntime | Out-Null } catch { $cleanupFailure = $true; if ($null -eq $cleanupFailureReason) { $cleanupFailureReason = (($_.Exception.Message -replace '[\r\n]+',' ').Trim()) } } }
    if (Test-Path -LiteralPath $credentialSnapshot -PathType Leaf) { $credentialSnapshotCreated = $true }
    if ($credentialSnapshotCreated) {
        try { $restore = Invoke-JsonPhp $fixtureHelper @('--action=restore',"--root=$root","--database=$database","--snapshot=$credentialSnapshot"); $restored = $restore.ExitCode -eq 0 -and [bool]$restore.Json.ok -and [bool]$restore.Json.restoration_hash_verified } catch { $restored = $false }
        if (-not $restored) { $integrityFailure = $true; $integrityFailureReason = 'La restauración de credenciales sintéticas no confirmó su hash.' }
        if ($restored) { Remove-Item -LiteralPath $credentialSnapshot -Force -ErrorAction SilentlyContinue }
    }
    if ($null -eq $baselineAfter -and $credentialSnapshotCreated) { try { $baselineAfter = Invoke-BaselineCompare } catch { $integrityFailure = $true; if ($null -eq $integrityFailureReason) { $integrityFailureReason = 'La comparación de la línea base M07 no pudo completarse.' } } }
}

$outcome = if ($cleanupFailure) { 'CLEANUP_FAILED' } elseif ($integrityFailure) { 'INTEGRITY_FAILED' } elseif ($null -ne $failure) { 'BLOCKED_OR_FAILED' } else { 'PASS' }
$out = [ordered]@{
    PHASE = 'M07_B1_HTTP_READ_ONLY_ACCEPTANCE'
    DATABASE = $database
    PATIENTS = '9301_9302_ONLY'
    DOCTORS = '9201_9202_ONLY'
    DOCTOR_A_LOGIN = $doctorLoginA
    DOCTOR_B_LOGIN = $doctorLoginB
    MATRIX_INVOKED = if ($matrixInvoked) { 'YES' } else { 'NO' }
    MATRIX_EXIT_CODE = $matrixExitCode
    APACHE_START_ATTEMPTED = $apacheStartAttempted
    APACHE_START_RESULT = $apacheStartResult
    RUNTIME_RECOVERED = $preflightRuntimeRecovered
    PREFLIGHT_HTTP_ATTEMPTED = $preflightHttpAttempted
    PREFLIGHT_HTTP_STATUS = $preflightHttpStatus
    PREFLIGHT_HTTP_BODY_CLASSIFICATION = $preflightHttpBodyClass
    PREFLIGHT_HTTP_RESULT = $preflightHttpResult
    PREFLIGHT_B1_STATUS = $preflightStatus
    PREFLIGHT_B1_EXIT_CODE = $preflightExitCode
    PREFLIGHT_B1_FIRST_FAILED_STEP = $preflightFirstFailedStep
    PREFLIGHT_B1_ERROR = $preflightError
    FIRST_FAILED_STAGE = $failureStage
    FIRST_FAILED_PROCESS_ID = $failureProcessId
    FIRST_FAILED_PROCESS_KIND = $failureProcessKind
    FIRST_FAILED_OPERATION = $failureOperation
    LAST_COMPLETED_CHECKPOINT = $lastCompletedCheckpoint
    P04_GUARD = if ($null -ne $p04 -and $p04.ExitCode -eq 0 -and [bool]$p04.Json.ok) { 'PASS' } else { 'NOT_REACHED_OR_FAIL' }
    PERMANENT_DATABASE_GUARD = if ($preflightHttpResult -eq 'PASS' -or $null -ne $normalRuntime) { 'PASS' } else { 'NOT_REACHED_OR_CLEANED' }
    CREDENTIAL_RESTORATION = if ($restored) { 'PASS_HASH_VERIFIED' } else { 'NOT_REACHED_OR_FAIL' }
    CLINICAL_BASELINE = if ($null -ne $baselineAfter -and $baselineAfter.ExitCode -eq 0 -and [string]$baselineAfter.Json.status -eq 'PASS_BASELINE_MATCH') { 'PASS_BASELINE_MATCH' } else { 'NOT_REACHED_OR_FAIL' }
    CLINICAL_WRITES = 'NO'
    A5_HARNESS_MODIFIED = 'NO'
    FINALIZATION_ENABLED = 'NO'
    CANONICAL_ACTIVATION_GATE = 'CLOSED'
    CLEANUP = if ($cleanupFailure) { 'FAIL' } else { 'PASS_OR_NOT_NEEDED' }
    FAILURE = if ($null -eq $failure) { '' } else { $failure }
    CLEANUP_FAILURE = if ($null -eq $cleanupFailureReason) { '' } else { $cleanupFailureReason }
    INTEGRITY_FAILURE = if ($null -eq $integrityFailureReason) { '' } else { $integrityFailureReason }
    FINAL_STATUS = $outcome
} | ConvertTo-Json -Depth 6
Write-Output $outcome
Write-Output $out
if ($outcome -ne 'PASS') { exit 1 }
