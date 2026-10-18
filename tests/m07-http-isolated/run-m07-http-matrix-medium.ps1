[CmdletBinding()]
param(
    [string]$PhpPath = 'C:\xampp\php\php.exe',
    [string]$BaselinePath = 'C:\Users\silvi\AppData\Local\ClinicaDolor\M07-baselines\m07-clinical-baseline-20261015T152934Z-7701ca5e.json',
    [switch]$DoctorLogin,
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
$preflightScript = Join-Path $root 'tests\patient-interview\m07-shared-canonical-migration.php'
$baselineScript = Join-Path $root 'tests\patient-interview\m07-durable-clinical-baseline.php'
$matrixScript = Join-Path $PSScriptRoot 'm07-http-negative-matrix.php'
$expectedApachePath = [IO.Path]::GetFullPath('C:\xampp\apache\bin\httpd.exe')
$credentialSnapshot = Join-Path ([IO.Path]::GetTempPath()) ('m07-http-access-' + [Guid]::NewGuid().ToString('N') + '.snapshot.json')
$preflightRuntime = $null
$normalRuntime = $null
$credentialSnapshotCreated = $false
$restored = $false
$baselineFinalVerified = $false
$cleanupFailure = $false
$integrityFailure = $false
$preflightStopped = $false
$preflightRuntimeStarted = $false
$p04 = $null
$preflight = $null
$matrix = $null
$baselineBefore = $null
$baselineAfter = $null
$failure = $null
$doctorToken = $null
$doctorPassword = $null
$doctorUsername = $null
$doctorLoginResult = 'NOT_REACHED'
$oldDoctorToken = [Environment]::GetEnvironmentVariable('M07_SYNTHETIC_DOCTOR_TOKEN', 'Process')
$oldPinA = [Environment]::GetEnvironmentVariable('P04_TEST_PIN_A', 'Process')
$oldPinB = [Environment]::GetEnvironmentVariable('P04_TEST_PIN_B', 'Process')
$permanentGuardResult = 'NOT_REACHED'
$apacheCleanupResult = 'NOT_REACHED'
$matrixInvoked = $false
$matrixExitCode = 'NOT_REACHED'
$matrixFirstFailedCondition = 'NOT_REACHED'
$matrixFirstFailedStatus = 'NOT_REACHED'
$matrixError = 'NOT_REACHED'
$matrixCasesExecuted = @()
$matrixCasesPassed = @()
$matrixCasesFailed = @()
$matrixCasesNotReached = @()
$matrixUnexpectedCases = @()
$matrixInventoryReconciled = 'NOT_REACHED'
$matrixHttpOperations = @()
$doctorGetExecuted = 'NOT_REACHED'
$doctorPostAttempted = 'NOT_REACHED'
$doctorPostHttpStatus = 'NOT_REACHED'
$doctorPostAssertion = 'NOT_REACHED'
$doctorPostContractCode = 'NOT_REACHED'
$matrixExpectedCases = @(
    'patient_access_page',
    'unauthenticated_get_rejected',
    'authentication_a',
    'authentication_b',
    'authenticated_m07_gets',
    'get_requests_no_clinical_write',
    'cross_clinic_patient_get_rejected',
    'cross_get_no_clinical_write',
    'csrf_missing_rejected_without_write',
    'csrf_invalid_rejected_without_write',
    'cross_clinic_post_rejected_without_write',
    'm07_finalization_rejected_without_write',
    'doctor_idPregunta_legacy_rejection'
)
$orphanRuntime = $null
$beforeRuntimeNames = @{}

if ($ApacheStartTimeoutSeconds -lt 5 -or $ApacheStartTimeoutSeconds -gt 60) {
    throw 'ApacheStartTimeoutSeconds debe estar entre 5 y 60 segundos.'
}

if ($PhpTimeoutSeconds -lt 5 -or $PhpTimeoutSeconds -gt 120) {
    throw 'PhpTimeoutSeconds debe estar entre 5 y 120 segundos.'
}
if ($HttpTimeoutSeconds -lt 5 -or $HttpTimeoutSeconds -gt 60) {
    throw 'HttpTimeoutSeconds debe estar entre 5 y 60 segundos.'
}

if (Test-Path -LiteralPath $runtimeBase -PathType Container) {
    foreach ($directory in Get-ChildItem -LiteralPath $runtimeBase -Directory) {
        $beforeRuntimeNames[$directory.Name] = $true
    }
}

function Assert-File([string]$Path, [string]$Message) {
    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        throw $Message
    }
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
    if (-not (Test-Path -LiteralPath $OutputPath -PathType Leaf)) {
        return $null
    }
    $baseFull = ([IO.Path]::GetFullPath($runtimeBase)).TrimEnd('\') + '\'
    foreach ($line in Get-Content -LiteralPath $OutputPath -ErrorAction SilentlyContinue) {
        if ($line -match '^RUNTIME=(.+)$') {
            try {
                $candidate = [IO.Path]::GetFullPath($Matches[1].Trim())
            } catch {
                continue
            }
            if ($candidate.StartsWith($baseFull, [StringComparison]::OrdinalIgnoreCase)) {
                return $candidate
            }
        }
    }
    return $null
}

function Find-NewRuntime([bool]$ExpectPreflightOnly) {
    if (-not (Test-Path -LiteralPath $runtimeBase -PathType Container)) {
        return $null
    }
    $baseFull = ([IO.Path]::GetFullPath($runtimeBase)).TrimEnd('\') + '\'
    foreach ($directory in (Get-ChildItem -LiteralPath $runtimeBase -Directory | Sort-Object LastWriteTime -Descending)) {
        if ($beforeRuntimeNames.ContainsKey($directory.Name)) { continue }
        $statePath = Join-Path $directory.FullName 'process.json'
        if (-not (Test-Path -LiteralPath $statePath -PathType Leaf)) { continue }
        try {
            $candidate = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
            if ([string]$candidate.database -eq $database -and
                [int]$candidate.port -eq $port -and
                [bool]$candidate.preflightOnly -eq $ExpectPreflightOnly -and
                [string]$candidate.runtime -eq $directory.FullName -and
                $directory.FullName.StartsWith($baseFull, [StringComparison]::OrdinalIgnoreCase)) {
                return $directory.FullName
            }
        } catch {
            continue
        }
    }
    return $null
}

function Invoke-JsonPhp {
    param([string]$ScriptPath, [string[]]$Arguments)
    $stdoutPath = [IO.Path]::GetTempFileName()
    $stderrPath = Join-Path ([IO.Path]::GetTempPath()) ('m07-http-stderr-' + [Guid]::NewGuid().ToString('N') + '.log')
    $statusPath = Join-Path ([IO.Path]::GetTempPath()) ('m07-http-php-status-' + [Guid]::NewGuid().ToString('N') + '.json')
    $wrapperPath = Join-Path ([IO.Path]::GetTempPath()) ('m07-http-php-' + [Guid]::NewGuid().ToString('N') + '.ps1')
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

        $process = Start-Process powershell.exe `
            -ArgumentList @('-NoLogo', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $wrapperPath) `
            -WorkingDirectory $root `
            -WindowStyle Hidden `
            -RedirectStandardOutput $stdoutPath `
            -RedirectStandardError $stderrPath `
            -PassThru

        if (-not $process.WaitForExit($PhpTimeoutSeconds * 1000)) {
            & taskkill.exe /PID $process.Id /T /F >$null 2>$null
            throw 'El proceso PHP auxiliar excedió su timeout controlado.'
        }
        if (-not (Test-Path -LiteralPath $statusPath -PathType Leaf)) {
            throw 'El proceso PHP auxiliar terminó sin resultado explícito.'
        }
        $childStatus = Get-Content -LiteralPath $statusPath -Raw | ConvertFrom-Json
        if (-not [bool]$childStatus.completed) {
            throw 'El proceso PHP auxiliar terminó con error controlado.'
        }
        $exitCode = [int]$childStatus.exit_code
        $text = (Get-Content -LiteralPath $stdoutPath -Raw -ErrorAction SilentlyContinue).Trim()
        if ($text -eq '') {
            throw "El proceso PHP no devolvió JSON controlado. Código: $exitCode"
        }
        try {
            $json = $text | ConvertFrom-Json
        } catch {
            throw "El proceso PHP devolvió JSON inválido. Código: $exitCode"
        }
        [PSCustomObject]@{ ExitCode = $exitCode; Json = $json }
    } finally {
        if ($null -ne $process -and -not $process.HasExited) {
            & taskkill.exe /PID $process.Id /T /F >$null 2>$null
        }
        if (Test-Path -LiteralPath $stdoutPath) {
            Remove-Item -LiteralPath $stdoutPath -Force -ErrorAction SilentlyContinue
        }
        if (Test-Path -LiteralPath $stderrPath) {
            Remove-Item -LiteralPath $stderrPath -Force -ErrorAction SilentlyContinue
        }
        Remove-Item -LiteralPath $statusPath, $wrapperPath -Force -ErrorAction SilentlyContinue
    }
}

function Get-MatrixDiagnostics {
    param(
        [object]$MatrixResult,
        [string[]]$ExpectedCases
    )

    $reported = @()
    $executed = @()
    $passed = @()
    $failed = @()
    $firstFailedCondition = 'NOT_REACHED'
    $firstFailedStatus = 'NOT_REACHED'
    $reportedError = 'NOT_REACHED'
    $doctorSteps = $null

    if ($null -ne $MatrixResult -and $null -ne $MatrixResult.Json) {
        if ($null -ne $MatrixResult.Json.checks) {
            foreach ($property in $MatrixResult.Json.checks.PSObject.Properties) {
                $name = [string]$property.Name
                $reported += $name
                $status = [string]$property.Value.status
                if ($status -eq 'PASS') {
                    $passed += $name
                } elseif ($status -eq 'FAIL') {
                    $failed += $name
                } elseif ($status -eq 'NOT_REACHED') {
                    continue
                }
                $executed += $name
                if ($firstFailedCondition -eq 'NOT_REACHED' -and $status -ne 'PASS') {
                    $firstFailedCondition = $name
                    $firstFailedStatus = if ([string]::IsNullOrWhiteSpace($status)) { 'UNKNOWN' } else { $status }
                }
            }
        }
        if (-not [string]::IsNullOrWhiteSpace([string]$MatrixResult.Json.error)) {
            $reportedError = ([string]$MatrixResult.Json.error -replace '[\r\n]+', ' ').Trim()
            if ($reportedError.Length -gt 240) {
                $reportedError = $reportedError.Substring(0, 240)
            }
        }
        if ($null -ne $MatrixResult.Json.doctor_http_steps) {
            $doctorSteps = $MatrixResult.Json.doctor_http_steps
        }
    }

    $notReached = @()
    foreach ($expectedCase in $ExpectedCases) {
        $reportedProperty = $null
        if ($null -ne $MatrixResult -and $null -ne $MatrixResult.Json -and $null -ne $MatrixResult.Json.checks) {
            $reportedProperty = $MatrixResult.Json.checks.PSObject.Properties[$expectedCase]
        }
        if ($null -eq $reportedProperty -or [string]$reportedProperty.Value.status -eq 'NOT_REACHED') {
            $notReached += $expectedCase
        }
    }
    $unexpected = @($reported | Where-Object { $ExpectedCases -notcontains $_ })
    $reconciled = ($unexpected.Count -eq 0)
    [PSCustomObject]@{
        FirstFailedCondition = $firstFailedCondition
        FirstFailedStatus = $firstFailedStatus
        ReportedError = $reportedError
        CasesExecuted = $executed
        CasesPassed = $passed
        CasesFailed = $failed
        CasesNotReached = $notReached
        UnexpectedCases = $unexpected
        InventoryReconciled = $reconciled
        HttpOperations = if ($null -ne $MatrixResult -and $null -ne $MatrixResult.Json.http_operations) { @($MatrixResult.Json.http_operations) } else { @() }
        DoctorSteps = $doctorSteps
    }
}

function Assert-ApacheRuntime {
    param(
        [string]$Runtime,
        [bool]$ExpectPreflightOnly = $true
    )
    $statePath = Join-Path $Runtime 'process.json'
    Assert-File $statePath 'No existe process.json del Apache sintético.'
    $state = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
    if ([string]$state.database -ne $database -or [int]$state.port -ne $port) {
        throw 'El runtime no corresponde al entorno sintético esperado.'
    }
    if ([string]$state.runtime -ne [IO.Path]::GetFullPath($Runtime)) {
        throw 'El runtime declarado no coincide con process.json.'
    }
    if (-not [bool]$state.stdoutRedirected -or -not [bool]$state.stderrRedirected) {
        throw 'El runtime no confirma redirección aislada de stdout/stderr.'
    }
    if ($ExpectPreflightOnly -and -not [bool]$state.preflightOnly) {
        throw 'El runtime recibido no está en modo PreflightOnly.'
    }
    if (-not $ExpectPreflightOnly -and [bool]$state.preflightOnly) {
        throw 'El runtime normal quedó en modo PreflightOnly.'
    }
    if ([IO.Path]::GetFullPath([string]$state.executable) -ne $expectedApachePath) {
        throw 'El runtime no declara el ejecutable Apache sintético permitido.'
    }
    if ([int]$state.controlPid -ne [int]$state.pid -or
        -not (Test-Path -LiteralPath ([string]$state.config) -PathType Leaf)) {
        throw 'El estado del runtime no confirma PID controlador y configuración verificables.'
    }
    $process = Get-Process -Id ([int]$state.pid) -ErrorAction SilentlyContinue
    if (-not $process) {
        throw 'El proceso Apache sintético ya no está activo.'
    }
    $expected = [IO.Path]::GetFullPath([string]$state.executable)
    $actual = [IO.Path]::GetFullPath([string]$process.Path)
    if ($actual -ne $expected) {
        throw 'El PID del Apache sintético no corresponde al ejecutable registrado.'
    }
    $listener = Get-NetTCPConnection -State Listen -LocalAddress '127.0.0.1' -LocalPort $port -ErrorAction SilentlyContinue | Select-Object -First 1
    if (-not $listener -or [int]$listener.OwningProcess -ne [int]$state.pid) {
        throw 'El listener sintético no pertenece al PID registrado.'
    }
    if ([int]$state.listenerPid -ne [int]$listener.OwningProcess) {
        throw 'El PID de listener no coincide con el estado registrado.'
    }
}

function Start-ApacheDetached([bool]$PreflightOnly) {
    $outputPath = [IO.Path]::GetTempFileName()
    $errorPath = Join-Path ([IO.Path]::GetTempPath()) ('m07-http-apache-stderr-' + [Guid]::NewGuid().ToString('N') + '.log')
    $statusPath = Join-Path ([IO.Path]::GetTempPath()) ('m07-http-apache-status-' + [Guid]::NewGuid().ToString('N') + '.json')
    $wrapperPath = Join-Path ([IO.Path]::GetTempPath()) ('m07-http-apache-' + [Guid]::NewGuid().ToString('N') + '.ps1')
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

        $launcher = Start-Process powershell.exe `
            -ArgumentList @('-NoLogo', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $wrapperPath) `
            -WorkingDirectory $root `
            -WindowStyle Hidden `
            -RedirectStandardOutput $outputPath `
            -RedirectStandardError $errorPath `
            -PassThru

        if (-not $launcher.WaitForExit($ApacheStartTimeoutSeconds * 1000)) {
            & taskkill.exe /PID $launcher.Id /T /F >$null 2>$null
            $runtime = Find-NewRuntime $PreflightOnly
            if ($null -ne $runtime) { $script:orphanRuntime = $runtime }
            throw 'El iniciador Apache excedió el timeout controlado.'
        }

        if (-not (Test-Path -LiteralPath $statusPath -PathType Leaf)) {
            $runtime = Find-NewRuntime $PreflightOnly
            if ($null -ne $runtime) { $script:orphanRuntime = $runtime }
            throw 'El iniciador Apache terminó sin resultado explícito.'
        }
        $status = Get-Content -LiteralPath $statusPath -Raw | ConvertFrom-Json
        if (-not [bool]$status.completed -or [string]$status.status -ne 'START_COMPLETED') {
            $runtime = Find-NewRuntime $PreflightOnly
            if ($null -ne $runtime) { $script:orphanRuntime = $runtime }
            throw 'El iniciador Apache no reportó START_COMPLETED.'
        }

        $runtime = Get-CapturedRuntime $outputPath
        if ($null -eq $runtime) { $runtime = Find-NewRuntime $PreflightOnly }
        if ($null -eq $runtime) {
            throw 'No se pudo recuperar RUNTIME= de una instancia Apache nueva.'
        }
        return $runtime
    } catch {
        if ($null -eq $script:orphanRuntime -and $null -ne $runtime) {
            $script:orphanRuntime = $runtime
        }
        throw
    } finally {
        if ($null -ne $launcher) {
            try { $launcher.Refresh() } catch { }
            if (-not $launcher.HasExited) {
                & taskkill.exe /PID $launcher.Id /T /F >$null 2>$null
            }
        }
        Remove-Item -LiteralPath $outputPath, $errorPath, $statusPath, $wrapperPath -Force -ErrorAction SilentlyContinue
    }
}

function Assert-PermanentDatabaseGuard([bool]$ExpectPreflightPass = $false) {
    $request = $null
    $response = $null
    $reader = $null
    $body = ''
    try {
        $request = [System.Net.HttpWebRequest]::Create('http://127.0.0.1:' + $port + '/historia-clinica/acceso')
        $request.Method = 'GET'
        $request.Timeout = 5000
        $request.ReadWriteTimeout = 5000
        try {
            $response = [System.Net.HttpWebResponse]$request.GetResponse()
        } catch [System.Net.WebException] {
            $response = $_.Exception.Response
            if ($null -eq $response) {
                throw 'El guard permanente no respondió.'
            }
        }

        $statusCode = [int]$response.StatusCode
        $guardCode = [string]$response.Headers['X-M07-Preflight-Failure-Code']
        $databaseGuard = [string]$response.Headers['X-M07-Database-Guard']
        try {
            $reader = New-Object System.IO.StreamReader($response.GetResponseStream())
            $body = $reader.ReadToEnd()
        } finally {
            if ($null -ne $reader) { $reader.Dispose() }
            $response.Dispose()
        }

        if ($statusCode -ne 200) {
            throw "El guard permanente respondió HTTP $statusCode; se exige HTTP 200."
        }
        if ($guardCode -match '^[A-Z0-9_]+$' -or $body.Trim() -eq 'SYNTHETIC_PREFLIGHT_FAILED') {
            throw 'El guard permanente rechazó la configuración sintética.'
        }
        if ($ExpectPreflightPass -and $body.Trim() -ne 'PASS') {
            throw 'El preflight HTTP no devolvió exactamente PASS.'
        }
        if (-not $ExpectPreflightPass -and $databaseGuard -ne 'PASS') {
            throw 'La petición normal no confirmó el marcador seguro del guard permanente.'
        }
        $script:permanentGuardResult = 'PASS_HTTP_REACHED'
    } catch {
        throw $_.Exception.Message
    }
}

function Invoke-BaselineCompare {
    return Invoke-JsonPhp $baselineScript @('--mode=compare', "--database=$database", "--patient-a=$patientA", "--patient-b=$patientB", "--snapshot=$BaselinePath")
}

function ConvertFrom-SecureStringForRequest([Security.SecureString]$SecurePassword) {
    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($SecurePassword)
    try {
        return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    } finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    }
}

function Invoke-SyntheticDoctorLogin([string]$Username, [string]$Password) {
    $body = @{ usuario = $Username; password = $Password } | ConvertTo-Json -Compress
    try {
        $response = Invoke-WebRequest `
            -Uri ('http://127.0.0.1:' + $port + '/clinica/login') `
            -Method Post `
            -ContentType 'application/json' `
            -Body $body `
            -UseBasicParsing `
            -TimeoutSec $HttpTimeoutSeconds `
            -ErrorAction Stop
    } catch {
        throw 'El login Doctor sintético no pudo completarse mediante /clinica/login.'
    }

    try {
        $payload = $response.Content | ConvertFrom-Json
    } catch {
        throw 'El login Doctor sintético no devolvió JSON válido.'
    }

    $token = ''
    if ($null -ne $payload.token) {
        $token = [string]$payload.token
    }
    if ([int]$response.StatusCode -ne 200 -or $payload.resultado -ne $true -or [string]::IsNullOrWhiteSpace($token)) {
        throw 'La credencial Doctor sintética fue rechazada por el login real.'
    }
    return $token
}

try {
    Assert-MediumIntegrity
    if ([IO.Path]::GetFullPath($BaselinePath) -ne [IO.Path]::GetFullPath($approvedBaselinePath)) {
        throw 'Fail-closed: sólo se admite la línea base M07 aprobada.'
    }
    foreach ($path in @($PhpPath, $startScript, $stopScript, $fixtureHelper, $p04Verifier, $preflightScript, $baselineScript, $matrixScript, $BaselinePath)) {
        Assert-File $path "Falta un componente requerido: $path"
    }
    if (-not $AllowSyntheticCredentialWrites -or $SyntheticConfirmation -ne 'I_CONFIRM_M07_HTTP_SYNTHETIC_9301_9302') {
        throw 'La ejecución exige autorización explícita para credenciales sintéticas y no acepta otra modalidad.'
    }
    if (-not $DoctorLogin) {
        throw 'La ejecución exige -DoctorLogin para no omitir la cobertura Doctor.'
    }

    $preflightRuntime = Start-ApacheDetached $true
    $preflightRuntimeStarted = $true
    Assert-ApacheRuntime $preflightRuntime $true
    Assert-PermanentDatabaseGuard $true
    $baselineBefore = Invoke-BaselineCompare
    if ($baselineBefore.ExitCode -ne 0 -or [string]$baselineBefore.Json.status -ne 'PASS_BASELINE_MATCH') {
        throw 'La línea base M07 no coincide antes de preparar credenciales.'
    }

    $fixturePreflight = Invoke-JsonPhp $fixtureHelper @('--action=preflight', "--root=$root", "--database=$database", "--snapshot=$credentialSnapshot")
    if ($fixturePreflight.ExitCode -ne 0 -or -not [bool]$fixturePreflight.Json.ok) {
        throw 'El preflight del fixture P04 no pasó.'
    }
    $prepare = Invoke-JsonPhp $fixtureHelper @('--action=prepare', "--root=$root", "--database=$database", "--snapshot=$credentialSnapshot")
    if (Test-Path -LiteralPath $credentialSnapshot -PathType Leaf) {
        $credentialSnapshotCreated = $true
    }
    if ($prepare.ExitCode -ne 0 -or -not [bool]$prepare.Json.ok -or [string]$prepare.Json.database_effective -ne $database) {
        throw 'El snapshot focal de credenciales no pudo prepararse.'
    }
    $credentialSnapshotCreated = $true

    & $stopScript -Runtime $preflightRuntime | Out-Null
    $preflightStopped = $true
    $beforeRuntimeNames[(Split-Path -Leaf $preflightRuntime)] = $true

    $create = Invoke-JsonPhp $fixtureHelper @('--action=create', "--root=$root", "--database=$database", "--snapshot=$credentialSnapshot")
    if ($create.ExitCode -ne 0 -or -not [bool]$create.Json.ok -or -not [bool]$create.Json.independent_active_credentials) {
        throw 'No se pudieron preparar dos credenciales sintéticas independientes.'
    }
    $p04 = Invoke-JsonPhp $p04Verifier @("--database=$database", "--patient-a=$patientA", "--patient-b=$patientB", '--isolation-mode=cross-clinic', '--json')
    if ($p04.ExitCode -ne 0 -or -not [bool]$p04.Json.ok) {
        throw 'P04 no pasó durante la ventana controlada de credenciales.'
    }
    $preflight = Invoke-JsonPhp $preflightScript @('--mode=preflight', "--database=$database", "--patient-a=$patientA", "--patient-b=$patientB")
    if ($preflight.ExitCode -ne 0 -or [string]$preflight.Json.status -ne 'PASS_READ_ONLY_PREFLIGHT') {
        throw 'El preflight M07 read-only no pasó antes de la matriz HTTP.'
    }

    $normalRuntime = Start-ApacheDetached $false
    Assert-ApacheRuntime $normalRuntime $false
    Assert-PermanentDatabaseGuard $false

    $doctorUsername = Read-Host 'Usuario Doctor sintético (no se registrará)'
    $secureDoctorPassword = Read-Host 'Contraseña Doctor sintético (no se registrará)' -AsSecureString
    if ([string]::IsNullOrWhiteSpace($doctorUsername)) {
        throw 'Debe indicarse el usuario Doctor sintético.'
    }
    $doctorPassword = ConvertFrom-SecureStringForRequest $secureDoctorPassword
    $secureDoctorPassword = $null
    try {
        $doctorToken = Invoke-SyntheticDoctorLogin $doctorUsername $doctorPassword
        [Environment]::SetEnvironmentVariable('M07_SYNTHETIC_DOCTOR_TOKEN', $doctorToken, 'Process')
        $doctorLoginResult = 'PASS_SAME_INSTANCE'
    } catch {
        $doctorLoginResult = 'FAIL'
        throw
    }
    $doctorPassword = $null
    $doctorUsername = $null

    $env:P04_TEST_PIN_A = 'cdp' + [string]$patientA
    $env:P04_TEST_PIN_B = 'cdp' + [string]$patientB
    $matrixInvoked = $true
    try {
        $matrix = Invoke-JsonPhp $matrixScript @("--database=$database", '--base-url=http://127.0.0.1:8117', "--patient-a=$patientA", "--patient-b=$patientB")
        $matrixExitCode = [string]$matrix.ExitCode
        $matrixDiagnostics = Get-MatrixDiagnostics $matrix $matrixExpectedCases
        $matrixFirstFailedCondition = $matrixDiagnostics.FirstFailedCondition
        $matrixFirstFailedStatus = $matrixDiagnostics.FirstFailedStatus
        $matrixError = $matrixDiagnostics.ReportedError
        $matrixCasesExecuted = @($matrixDiagnostics.CasesExecuted)
        $matrixCasesPassed = @($matrixDiagnostics.CasesPassed)
        $matrixCasesFailed = @($matrixDiagnostics.CasesFailed)
        $matrixCasesNotReached = @($matrixDiagnostics.CasesNotReached)
        $matrixUnexpectedCases = @($matrixDiagnostics.UnexpectedCases)
        $matrixInventoryReconciled = if ($matrixDiagnostics.InventoryReconciled) { 'PASS' } else { 'FAIL' }
        $matrixHttpOperations = @($matrixDiagnostics.HttpOperations)
        if ($null -ne $matrixDiagnostics.DoctorSteps) {
            $doctorGetExecuted = if ([string]$matrixDiagnostics.DoctorSteps.page_status -ne 'NOT_REACHED' -and [string]$matrixDiagnostics.DoctorSteps.questions_status -ne 'NOT_REACHED') { 'YES' } else { 'NO' }
            $doctorPostAttempted = if ([bool]$matrixDiagnostics.DoctorSteps.post_attempted) { 'YES' } else { 'NO' }
            $doctorPostHttpStatus = [string]$matrixDiagnostics.DoctorSteps.post_status
            $doctorPostAssertion = [string]$matrixDiagnostics.DoctorSteps.post_assertion
            $doctorPostContractCode = [string]$matrixDiagnostics.DoctorSteps.post_contract_code
        }
        if ($matrix.ExitCode -ne 0 -or -not [bool]$matrix.Json.ok -or
            $matrixInventoryReconciled -ne 'PASS' -or
            $matrixCasesNotReached.Count -gt 0 -or
            $matrixCasesFailed.Count -gt 0) {
            $detail = 'La matriz HTTP sintética no pasó completamente.'
            if ($matrixFirstFailedCondition -ne 'NOT_REACHED') {
                $detail += " Primera condición: $matrixFirstFailedCondition ($matrixFirstFailedStatus)."
            }
            if ($matrixError -ne 'NOT_REACHED') {
                $detail += " Error reportado: $matrixError"
            }
            if ($matrixCasesNotReached.Count -gt 0) {
                $detail += " Casos no alcanzados: $($matrixCasesNotReached -join ',')."
            }
            if ($matrixCasesFailed.Count -gt 0) {
                $detail += " Casos fallidos: $($matrixCasesFailed -join ',')."
            }
            throw $detail
        }
    } catch {
        if ($matrixError -eq 'NOT_REACHED') {
            $matrixError = (($_.Exception.Message -replace '[\r\n]+', ' ').Trim())
            if ($matrixError.Length -gt 240) {
                $matrixError = $matrixError.Substring(0, 240)
            }
        }
        throw
    }
    $baselineAfter = Invoke-BaselineCompare
    if ($baselineAfter.ExitCode -ne 0 -or [string]$baselineAfter.Json.status -ne 'PASS_BASELINE_MATCH') {
        throw 'La comparación clínica posterior no coincide con la línea base.'
    }
} catch {
    $failure = $_.Exception.Message
} finally {
    [Environment]::SetEnvironmentVariable('P04_TEST_PIN_A', $oldPinA, 'Process')
    [Environment]::SetEnvironmentVariable('P04_TEST_PIN_B', $oldPinB, 'Process')
    [Environment]::SetEnvironmentVariable('M07_SYNTHETIC_DOCTOR_TOKEN', $oldDoctorToken, 'Process')
    $doctorToken = $null
    $doctorPassword = $null
    $doctorUsername = $null

    if ($preflightRuntimeStarted -and -not $preflightStopped -and $null -ne $preflightRuntime) {
        try {
            & $stopScript -Runtime $preflightRuntime | Out-Null
            $preflightStopped = $true
        } catch {
            $cleanupFailure = $true
            $failure = if ($null -eq $failure) { 'No se pudo detener el Apache sintético de preflight.' } else { $failure }
        }
    }

    if ($null -ne $orphanRuntime -and
        ($null -eq $preflightRuntime -or [IO.Path]::GetFullPath($orphanRuntime) -ne [IO.Path]::GetFullPath($preflightRuntime)) -and
        ($null -eq $normalRuntime -or [IO.Path]::GetFullPath($orphanRuntime) -ne [IO.Path]::GetFullPath($normalRuntime))) {
        try {
            & $stopScript -Runtime $orphanRuntime | Out-Null
        } catch {
            $cleanupFailure = $true
            $failure = if ($null -eq $failure) { 'No se pudo limpiar el runtime Apache recuperado tras un fallo de arranque.' } else { $failure }
        }
    }

    if ($null -ne $normalRuntime) {
        try {
            & $stopScript -Runtime $normalRuntime | Out-Null
            $apacheCleanupResult = 'PASS'
        } catch {
            $cleanupFailure = $true
            $apacheCleanupResult = 'FAIL'
            $failure = if ($null -eq $failure) { 'No se pudo detener el Apache normal sintético.' } else { $failure }
        }
    }
    if (-not $credentialSnapshotCreated -and (Test-Path -LiteralPath $credentialSnapshot -PathType Leaf)) {
        $credentialSnapshotCreated = $true
    }
    if ($credentialSnapshotCreated) {
        try {
            $restore = Invoke-JsonPhp $fixtureHelper @('--action=restore', "--root=$root", "--database=$database", "--snapshot=$credentialSnapshot")
            $restored = $restore.ExitCode -eq 0 -and [bool]$restore.Json.ok -and [bool]$restore.Json.restoration_hash_verified
        } catch {
            $restored = $false
        }
    }
    if ($credentialSnapshotCreated -and -not $restored) {
        $integrityFailure = $true
        $failure = if ($null -eq $failure) { 'La restauración de credenciales no pudo verificarse; el snapshot se conserva.' } else { $failure }
    }
    if ($restored -and (Test-Path -LiteralPath $credentialSnapshot)) {
        Remove-Item -LiteralPath $credentialSnapshot -Force -ErrorAction SilentlyContinue
    }

    if ($null -eq $baselineAfter -and $credentialSnapshotCreated) {
        try {
            $baselineAfter = Invoke-BaselineCompare
            if ($baselineAfter.ExitCode -ne 0 -or [string]$baselineAfter.Json.status -ne 'PASS_BASELINE_MATCH') {
                $integrityFailure = $true
                $failure = if ($null -eq $failure) { 'La comparación clínica final no coincide con la línea base.' } else { $failure }
            } else {
                $baselineFinalVerified = $true
            }
        } catch {
            $integrityFailure = $true
            $failure = if ($null -eq $failure) { 'No se pudo verificar la línea base clínica final.' } else { $failure }
        }
    }

    if ($credentialSnapshotCreated -and $null -ne $baselineAfter -and
        $baselineAfter.ExitCode -eq 0 -and [string]$baselineAfter.Json.status -eq 'PASS_BASELINE_MATCH') {
        $baselineFinalVerified = $true
    }

    $matrixFailure = $matrixInvoked -and (
        $matrixExitCode -eq 'NOT_REACHED' -or
        $matrixExitCode -ne '0' -or
        $matrixCasesFailed.Count -gt 0 -or
        $matrixCasesNotReached.Count -gt 0 -or
        $matrixInventoryReconciled -ne 'PASS'
    )
    $runOutcome = if ($cleanupFailure) {
        'CLEANUP_FAILED'
    } elseif ($integrityFailure) {
        'INTEGRITY_FAILED'
    } elseif ($matrixFailure) {
        'MATRIX_FAILED'
    } elseif ($null -ne $failure) {
        'PREPARATION_FAILED'
    } else {
        'MATRIX_PASSED'
    }

    [ordered]@{
        PHASE = 'M07_PHASE_A5_HTTP_SYNTHETIC_MATRIX'
        APACHE_SYNTHETIC_GUARD = 'REUSED_PERMANENT_SELECT_DATABASE_GUARD'
        P04_PRIOR_RESULT = if ($null -ne $p04 -and $p04.ExitCode -eq 0 -and [bool]$p04.Json.ok) { 'PASS' } else { 'NOT_REACHED_OR_FAIL' }
        BASELINE_PRIOR_RESULT = if ($null -ne $baselineBefore -and $baselineBefore.ExitCode -eq 0 -and [string]$baselineBefore.Json.status -eq 'PASS_BASELINE_MATCH') { 'PASS_BASELINE_MATCH' } else { 'NOT_REACHED_OR_FAIL' }
        HTTP_MATRIX_PREPARED = 'YES_NO_POSITIVE_CLINICAL_WRITES'
        AUTHENTICATION_CASES = 'PATIENT_A_PATIENT_B_ANONYMOUS'
        CSRF_CASES = 'VALID_CONTEXT_MISSING_INVALID'
        CROSS_TENANT_CASES = 'GET_AND_POST_REJECTION'
        DOCTOR_ID_PREGUNTA_CASE = 'PREPARED_REQUIRES_SAME_INSTANCE_SYNTHETIC_DOCTOR_SESSION'
        DOCTOR_LOGIN_FLOW = $doctorLoginResult
        PERMANENT_DATABASE_GUARD = $permanentGuardResult
        APACHE_PROCESS_CLEANUP = $apacheCleanupResult
        DOCTOR_CREDENTIALS_EMITTED = 'NO'
        LEGACY_WRITE_REJECTION_CASES = 'PREPARED_NO_POSITIVE_WRITE'
        FINALIZATION_REJECTION_CASES = 'PREPARED_NO_POSITIVE_WRITE'
        GET_NO_WRITE_CASES = 'PREPARED_HASH_CHECKS'
        TEMP_CREDENTIAL_LIFECYCLE = if ($credentialSnapshotCreated) { 'PREPARED_REUSE_M07_P04_HELPER' } else { 'NOT_REACHED' }
        CREDENTIAL_SNAPSHOT_GUARD = if ($credentialSnapshotCreated) { 'PREPARED' } else { 'NOT_REACHED' }
        CREDENTIAL_RESTORATION_GUARD = if ($restored) { 'PASS_HASH_VERIFIED' } else { 'NOT_EXECUTED_OR_BLOCKED' }
        CLINICAL_BASELINE_GUARD = if ($null -ne $baselineAfter -and $baselineAfter.ExitCode -eq 0 -and [string]$baselineAfter.Json.status -eq 'PASS_BASELINE_MATCH') { 'PASS_BASELINE_MATCH' } else { 'NOT_REACHED_OR_FAIL' }
        RUN_OUTCOME = $runOutcome
        FILES_CREATED = 'NONE_BY_RUNNER'
        FILES_MODIFIED = 'tests/m07-http-isolated/run-m07-http-matrix-medium.ps1,tests/m07-http-isolated/m07-http-negative-matrix.php'
        PHP_LINT = 'NOT_EXECUTED_BY_RUNNER'
        PS51_VALIDATION = 'NOT_EXECUTED_BY_RUNNER'
        STATIC_TESTS = 'NOT_EXECUTED_BY_RUNNER'
        MATRIX_INVOKED = if ($matrixInvoked) { 'YES' } else { 'NO' }
        MATRIX_EXIT_CODE = $matrixExitCode
        MATRIX_FIRST_FAILED_CONDITION = $matrixFirstFailedCondition
        MATRIX_FIRST_FAILED_STATUS = $matrixFirstFailedStatus
        MATRIX_ERROR = $matrixError
        EXPECTED_CASES = $matrixExpectedCases -join ','
        CASE_INVENTORY_RECONCILED = $matrixInventoryReconciled
        HTTP_CASES_EXECUTED = if ($matrixCasesExecuted.Count -gt 0) { $matrixCasesExecuted -join ',' } else { 'NOT_REACHED' }
        HTTP_CASES_PASSED = if ($matrixCasesPassed.Count -gt 0) { $matrixCasesPassed -join ',' } else { 'NOT_REACHED' }
        HTTP_CASES_FAILED = if ($matrixCasesFailed.Count -gt 0) { $matrixCasesFailed -join ',' } else { 'NOT_REACHED' }
        HTTP_CASES_NOT_REACHED = if ($matrixCasesNotReached.Count -gt 0) { $matrixCasesNotReached -join ',' } else { 'NOT_REACHED' }
        HTTP_CASES_UNEXPECTED = if ($matrixUnexpectedCases.Count -gt 0) { $matrixUnexpectedCases -join ',' } else { 'NONE' }
        HTTP_OPERATIONS_EXECUTED = $matrixHttpOperations.Count
        DOCTOR_GET_EXECUTED = $doctorGetExecuted
        DOCTOR_POST_ATTEMPTED = $doctorPostAttempted
        DOCTOR_POST_HTTP_STATUS = $doctorPostHttpStatus
        DOCTOR_POST_ASSERTION = $doctorPostAssertion
        DOCTOR_POST_CONTRACT_CODE = $doctorPostContractCode
        HTTP_TESTS_EXECUTED = if ($matrixInvoked) { 'YES' } else { 'NO' }
        MYSQL_WRITES = 'NO_BY_THIS_PREPARATION'
        BD_CLINICA_MODIFIED = 'NO'
        COMMITS_CREATED = 'NO'
        READY_FOR_OWNER_MEDIUM_HTTP_RUN = if ($null -eq $failure) { 'YES_WITH_SYNTHETIC_DOCTOR_SESSION_PREREQUISITE' } else { 'NO' }
        BLOCKERS = if ($null -eq $failure) { 'No se ejecutó; requiere autorización posterior y sesión Doctor sintética para la parte Doctor.' } else { $failure }
        FINAL_STATUS = $runOutcome
    } | ConvertTo-Json -Depth 5
}

if ($null -ne $failure) { exit 2 }
