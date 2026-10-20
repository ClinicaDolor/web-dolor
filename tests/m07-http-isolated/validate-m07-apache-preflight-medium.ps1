param(
    [int]$Port = 8117,
    [int]$TimeoutSeconds = 15,
    [string]$ApacheExe = 'C:\xampp\apache\bin\httpd.exe',
    [switch]$ApplicationReadOnly
)

$ErrorActionPreference = 'Stop'

if ($Port -ne 8117) {
    throw 'Fail-closed: este validador sólo admite el puerto 8117.'
}
if ($TimeoutSeconds -lt 5 -or $TimeoutSeconds -gt 60) {
    throw 'El timeout debe estar entre 5 y 60 segundos.'
}

$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$startScript = Join-Path $PSScriptRoot 'start-m07-apache.ps1'
$stopScript = Join-Path $PSScriptRoot 'stop-m07-apache.ps1'
$database = 'clinica_dolor_demo_20261010_final'
$expectedApachePath = [IO.Path]::GetFullPath($ApacheExe)
$runtimeBase = Join-Path ([IO.Path]::GetTempPath()) 'clinica-m07-http-isolated'
$outputPath = [IO.Path]::GetTempFileName()
$errorPath = [IO.Path]::GetTempFileName()
$statusPath = [IO.Path]::GetTempFileName()
$wrapperPath = Join-Path ([IO.Path]::GetTempPath()) ('m07-apache-launch-' + [Guid]::NewGuid().ToString('N') + '.ps1')
$runtime = $null
$state = $null
$launcher = $null
$failure = $null
$exitCode = $null
$exitCodeStatus = 'NOT_REACHED'
$explicitResult = 'NOT_REACHED'
$httpStatus = 'NOT_REACHED'
$httpBody = 'NOT_REACHED'
$httpBodyClass = 'NOT_REACHED'
$httpBodyLength = 'NOT_REACHED'
$httpFailureType = 'NOT_REACHED'
$httpResult = 'NOT_REACHED'
$http503Source = 'NOT_REACHED'
$httpGuardCode = 'NOT_REACHED'
$httpCaseResults = @()
$cleanupStatus = 'NOT_REACHED'
$cleanupFailureDetail = 'NOT_REACHED'
$cleanupChecks = @()
$cleanupStopOutput = @()
$diagnosticsPreserved = 'NO'
$beforeRuntimeNames = @{}

if (-not (Test-Path -LiteralPath $startScript -PathType Leaf) -or
    -not (Test-Path -LiteralPath $stopScript -PathType Leaf)) {
    throw 'No están disponibles los scripts de inicio y detención sintética.'
}

if (Test-Path -LiteralPath $runtimeBase -PathType Container) {
    foreach ($directory in Get-ChildItem -LiteralPath $runtimeBase -Directory) {
        $beforeRuntimeNames[$directory.Name] = $true
    }
}

function Add-Failure([string]$Message) {
    if ([string]::IsNullOrEmpty($script:failure)) {
        $script:failure = $Message
    }
}

function Add-CleanupCheck([string]$Name, [string]$Resource, [string]$Expected, [string]$Observed, [string]$Result, [string]$Evidence) {
    $script:cleanupChecks += [ordered]@{
        name = $Name
        resource = $Resource
        expected = $Expected
        observed = $Observed
        result = $Result
        evidence = $Evidence
    }
}

function Sanitize-Diagnostic([object]$Value) {
    $text = [string]$Value
    if ([string]::IsNullOrWhiteSpace($text)) { return 'EMPTY' }
    $text = $text -replace '[\r\n]+', ' '
    if ($text.Length -gt 400) { $text = $text.Substring(0, 400) }
    return $text
}

function ConvertTo-PowerShellLiteral([string]$Value) {
    return ("'" + $Value.Replace("'", "''") + "'")
}

function Get-CapturedRuntime {
    if (-not (Test-Path -LiteralPath $outputPath -PathType Leaf)) { return $null }
    $baseFull = ([IO.Path]::GetFullPath($runtimeBase)).TrimEnd('\') + '\'
    foreach ($line in Get-Content -LiteralPath $outputPath -ErrorAction SilentlyContinue) {
        if ($line -match '^RUNTIME=(.+)$') {
            $candidate = [IO.Path]::GetFullPath($Matches[1].Trim())
            if ($candidate.StartsWith($baseFull, [StringComparison]::OrdinalIgnoreCase)) {
                return $candidate
            }
        }
    }
    return $null
}

function Find-RecoveredRuntime {
    if (-not (Test-Path -LiteralPath $runtimeBase -PathType Container)) { return $null }
    $baseFull = ([IO.Path]::GetFullPath($runtimeBase)).TrimEnd('\') + '\'
    foreach ($directory in (Get-ChildItem -LiteralPath $runtimeBase -Directory | Sort-Object LastWriteTime -Descending)) {
        if ($beforeRuntimeNames.ContainsKey($directory.Name)) { continue }
        $statePath = Join-Path $directory.FullName 'process.json'
        if (-not (Test-Path -LiteralPath $statePath -PathType Leaf)) { continue }
        try {
            $candidate = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
            if ([string]$candidate.database -eq $database -and
                [int]$candidate.port -eq $Port -and
                ([bool]$candidate.preflightOnly -eq (-not [bool]$ApplicationReadOnly)) -and
                $directory.FullName.StartsWith($baseFull, [StringComparison]::OrdinalIgnoreCase)) {
                return $directory.FullName
            }
        } catch {
            continue
        }
    }
    return $null
}

function Read-And-ValidateRuntime([string]$RuntimePath) {
    $statePath = Join-Path $RuntimePath 'process.json'
    if (-not (Test-Path -LiteralPath $statePath -PathType Leaf)) {
        throw 'El runtime recuperado no tiene process.json.'
    }
    $candidate = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
    if ([string]$candidate.database -ne $database) { throw 'La base del runtime no es la sintética autorizada.' }
    if ([int]$candidate.port -ne $Port) { throw 'El puerto del runtime no coincide.' }
    if ([bool]$candidate.preflightOnly -eq [bool]$ApplicationReadOnly) { throw 'El modo del runtime no coincide con el modo solicitado.' }
    if ([string]$candidate.executable -ne $expectedApachePath) { throw 'El ejecutable del runtime no coincide.' }
    if (-not [bool]$candidate.stdoutRedirected -or -not [bool]$candidate.stderrRedirected) { throw 'El runtime no confirma redirección a archivos.' }

    $process = Get-Process -Id ([int]$candidate.pid) -ErrorAction SilentlyContinue
    if ($null -eq $process) { throw 'El proceso Apache del runtime no está activo.' }
    $actualPath = [IO.Path]::GetFullPath([string]$process.Path)
    if ($actualPath -ne $expectedApachePath) { throw 'El PID del runtime no corresponde al ejecutable Apache esperado.' }

    $listener = Get-NetTCPConnection -State Listen -LocalAddress '127.0.0.1' -LocalPort $Port -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($null -eq $listener) { throw 'El listener sintético no está activo.' }
    if ([int]$listener.OwningProcess -ne [int]$candidate.pid) { throw 'El listener no pertenece al PID registrado.' }
    return $candidate
}

function Invoke-HttpPreflight {
    $request = $null
    $response = $null
    $reader = $null
    $body = $null

    try {
        $request = [System.Net.HttpWebRequest]::Create('http://127.0.0.1:' + $Port + '/')
        $request.Method = 'GET'
        $request.Timeout = 5000
        $request.ReadWriteTimeout = 5000

        try {
            $response = [System.Net.HttpWebResponse]$request.GetResponse()
        } catch [System.Net.WebException] {
            $response = $_.Exception.Response
            if ($null -eq $response) {
                $script:httpStatus = 'TRANSPORT_ERROR'
                $script:httpBody = 'NOT_AVAILABLE'
                $script:httpBodyClass = 'NOT_AVAILABLE'
                $script:httpBodyLength = 'NOT_AVAILABLE'
                $script:httpFailureType = 'TRANSPORT'
                $script:httpResult = 'FAIL'
                $script:httpGuardCode = 'NOT_AVAILABLE'
                Add-Failure 'No fue posible recibir una respuesta HTTP del preflight.'
                return
            }
        }

        $statusCode = [int]$response.StatusCode
        $script:httpStatus = [string]$statusCode
        $script:httpFailureType = 'HTTP_RESPONSE'
        $rawGuardCode = [string]$response.Headers['X-M07-Preflight-Failure-Code']
        if ($rawGuardCode -match '^[A-Z0-9_]+$') {
            $script:httpGuardCode = $rawGuardCode
        } elseif (-not [string]::IsNullOrEmpty($rawGuardCode)) {
            $script:httpGuardCode = 'INVALID_HEADER_VALUE'
        } else {
            $script:httpGuardCode = 'NOT_PRESENT'
        }

        try {
            $reader = New-Object System.IO.StreamReader($response.GetResponseStream())
            $body = $reader.ReadToEnd()
        } finally {
            if ($null -ne $reader) { $reader.Dispose() }
            $response.Dispose()
        }

        $trimmedBody = ([string]$body).Trim()
        $script:httpBodyLength = [string]([string]$body).Length
        if ($trimmedBody -eq 'PASS') {
            $script:httpBodyClass = 'PASS'
        } elseif ($trimmedBody -eq 'FAIL') {
            $script:httpBodyClass = 'FAIL'
        } elseif ([string]::IsNullOrEmpty($trimmedBody)) {
            $script:httpBodyClass = 'EMPTY'
        } else {
            $script:httpBodyClass = 'OTHER'
        }

        # Mantener el campo histórico sin devolver el cuerpo crudo. El guard
        # sólo se acepta con HTTP 200 y cuerpo PASS; cualquier otro resultado
        # queda clasificado, no reinterpretado.
        $script:httpBody = $script:httpBodyClass
        if ($statusCode -eq 503) {
            $script:http503Source = 'web-database-preflight.php:$fail'
        }

        if ($statusCode -eq 200 -and $script:httpBodyClass -eq 'PASS') {
            $script:httpResult = 'PASS'
        } else {
            $script:httpResult = 'FAIL'
            if ($statusCode -eq 503 -and $script:httpBodyClass -eq 'FAIL') {
                Add-Failure 'El guard de preflight rechazó la petición (HTTP 503; cuerpo FAIL).'
            } else {
                Add-Failure 'El preflight HTTP no devolvió 200 con cuerpo PASS.'
            }
        }
    } catch {
        $script:httpStatus = 'CLIENT_ERROR'
        $script:httpBody = 'NOT_AVAILABLE'
        $script:httpBodyClass = 'NOT_AVAILABLE'
        $script:httpBodyLength = 'NOT_AVAILABLE'
        $script:httpFailureType = 'CLIENT'
        $script:httpResult = 'FAIL'
        $script:httpGuardCode = 'NOT_AVAILABLE'
        Add-Failure 'El cliente HTTP no pudo completar la verificación del preflight.'
    }
}

function Invoke-AnonymousApplicationReadOnly {
    $cases = @(
        @{ path = '/'; expectedStatus = 200; expectedRedirect = 'NONE' },
        @{ path = '/historia-clinica/acceso'; expectedStatus = 200; expectedRedirect = 'NONE' },
        @{ path = '/historia-clinica/entrevista'; expectedStatus = 302; expectedRedirect = 'PATIENT_ACCESS' },
        @{ path = '/clinica/acceso'; expectedStatus = 200; expectedRedirect = 'NONE' }
    )
    $failedCases = 0
    foreach ($case in $cases) {
        $request = [System.Net.HttpWebRequest]::Create('http://127.0.0.1:' + $Port + $case.path)
        $request.Method = 'GET'
        $request.AllowAutoRedirect = $false
        $request.Timeout = 7000
        $request.ReadWriteTimeout = 7000
        $request.CookieContainer = $null
        $watch = [Diagnostics.Stopwatch]::StartNew()
        $response = $null
        $transport = 'HTTP_RESPONSE'
        try {
            $response = [System.Net.HttpWebResponse]$request.GetResponse()
        } catch [System.Net.WebException] {
            $response = $_.Exception.Response
            if ($null -eq $response) { $transport = 'TRANSPORT_ERROR' }
        }
        $watch.Stop()
        if ($null -eq $response) {
            $script:httpCaseResults += [ordered]@{ path=$case.path; status='NO_RESPONSE'; database_guard='NOT_CONFIRMED'; redirect='NONE'; latency_ms=$watch.ElapsedMilliseconds; result='FAIL' }
            $failedCases++
            continue
        }
        $status = [int]$response.StatusCode
        $guard = [string]$response.Headers['X-M07-Database-Guard']
        $location = [string]$response.Headers['Location']
        $redirectClass = if ($location -match '/historia-clinica/acceso') { 'PATIENT_ACCESS' } elseif ($location -match '/clinica/acceso') { 'CLINIC_ACCESS' } elseif ([string]::IsNullOrEmpty($location)) { 'NONE' } else { 'OTHER_REDACTED' }
        $response.Dispose()
        $pass = $transport -eq 'HTTP_RESPONSE' -and $status -eq [int]$case.expectedStatus -and $guard -eq 'PASS' -and $redirectClass -eq [string]$case.expectedRedirect
        if (-not $pass) { $failedCases++ }
        $script:httpCaseResults += [ordered]@{
            path = $case.path
            status = $status
            database_guard = if ($guard -eq 'PASS') { 'PASS' } else { 'NOT_CONFIRMED' }
            redirect = $redirectClass
            latency_ms = $watch.ElapsedMilliseconds
            result = if ($pass) { 'PASS' } else { 'FAIL' }
        }
    }
    $script:httpResult = if ($failedCases -eq 0) { 'PASS' } else { 'FAIL' }
    $script:httpStatus = 'MULTI_CASE'
    $script:httpBody = 'NOT_CAPTURED'
    $script:httpBodyClass = 'NOT_CAPTURED'
    $script:httpFailureType = 'HTTP_READ_ONLY_CASES'
    if ($failedCases -gt 0) { Add-Failure 'Una o más rutas HTTP anónimas no cumplieron el contrato de solo lectura.' }
}

function Get-SyntheticProcessRecordsForState([object]$RuntimeState) {
    $expectedPath = [IO.Path]::GetFullPath([string]$RuntimeState.executable)
    $configPath = [IO.Path]::GetFullPath([string]$RuntimeState.config)
    $records = @(Get-CimInstance Win32_Process -Filter "Name = 'httpd.exe'" -ErrorAction Stop)
    return @($records | Where-Object {
        try {
            [IO.Path]::GetFullPath([string]$_.ExecutablePath) -eq $expectedPath -and
                ([string]$_.CommandLine).IndexOf($configPath, [StringComparison]::OrdinalIgnoreCase) -ge 0
        } catch { $false }
    })
}

function Test-IndependentRuntimeCleanup([object]$RuntimeState) {
    try {
        $rootProcess = Get-Process -Id ([int]$RuntimeState.pid) -ErrorAction SilentlyContinue
        $listener = Get-NetTCPConnection -State Listen -LocalAddress '127.0.0.1' -LocalPort $Port -ErrorAction SilentlyContinue | Select-Object -First 1
        $ownedProcesses = @(Get-SyntheticProcessRecordsForState $RuntimeState)
        return ($null -eq $rootProcess -and $null -eq $listener -and $ownedProcesses.Count -eq 0)
    } catch {
        return $false
    }
}

try {
    $wrapperLines = @(
        '$ErrorActionPreference = ''Stop'''
        'try {'
        ('    & ' + (ConvertTo-PowerShellLiteral $startScript) + ' -Port ' + [string]$Port + $(if ($ApplicationReadOnly) { '' } else { ' -PreflightOnly' }))
        ('    [ordered]@{ completed = $true; status = ''START_COMPLETED'' } | ConvertTo-Json -Compress | Set-Content -LiteralPath ' + (ConvertTo-PowerShellLiteral $statusPath) + ' -Encoding UTF8')
        '    exit 0'
        '} catch {'
        ('    [ordered]@{ completed = $false; status = ''START_FAILED'' } | ConvertTo-Json -Compress | Set-Content -LiteralPath ' + (ConvertTo-PowerShellLiteral $statusPath) + ' -Encoding UTF8')
        '    exit 1'
        '}'
    )
    Set-Content -LiteralPath $wrapperPath -Value ($wrapperLines -join [Environment]::NewLine) -Encoding UTF8

    $launcher = Start-Process powershell.exe `
        -ArgumentList @(
            '-NoLogo',
            '-NoProfile',
            '-ExecutionPolicy', 'Bypass',
            '-File', $wrapperPath
        ) `
        -WindowStyle Hidden `
        -RedirectStandardOutput $outputPath `
        -RedirectStandardError $errorPath `
        -PassThru

    $completed = $launcher.WaitForExit($TimeoutSeconds * 1000)
    if (-not $completed) {
        Add-Failure 'El proceso lanzador excedió el timeout externo.'
        & taskkill.exe /PID $launcher.Id /T /F >$null 2>$null
    } else {
        $launcher.Refresh()
        if (-not $launcher.HasExited) {
            Add-Failure 'El lanzador no confirmó su terminación.'
        } else {
            if (Test-Path -LiteralPath $statusPath -PathType Leaf) {
                try {
                    $explicitStatus = Get-Content -LiteralPath $statusPath -Raw | ConvertFrom-Json
                    if ([bool]$explicitStatus.completed -and [string]$explicitStatus.status -eq 'START_COMPLETED') {
                        $explicitResult = 'START_COMPLETED'
                    } else {
                        $explicitResult = 'START_FAILED'
                        Add-Failure 'El proceso hijo reportó un fallo explícito de arranque.'
                    }
                } catch {
                    $explicitResult = 'INVALID'
                    Add-Failure 'El resultado explícito del proceso hijo no es válido.'
                }
            } else {
                $explicitResult = 'NOT_AVAILABLE'
                Add-Failure 'Falta el resultado explícito del proceso hijo.'
            }

            # En Windows PowerShell 5.1 ExitCode puede permanecer null incluso
            # después de HasExited. Se conserva como diagnóstico, pero nunca se
            # usa como PASS si el resultado explícito no existe.
            try {
                $exitCode = $launcher.ExitCode
                if ($null -eq $exitCode -or [string]::IsNullOrWhiteSpace([string]$exitCode)) {
                    $exitCodeStatus = 'NOT_AVAILABLE'
                } else {
                    $exitCodeStatus = [string]$exitCode
                    if ([int]$exitCode -ne 0) { Add-Failure 'El lanzador terminó con código distinto de cero.' }
                }
            } catch {
                $exitCodeStatus = 'NOT_AVAILABLE'
            }
        }
    }

    $runtime = Get-CapturedRuntime
    if ($null -eq $runtime) { $runtime = Find-RecoveredRuntime }
    if ($null -eq $runtime) {
        Add-Failure 'No se pudo recuperar un runtime sintético verificable.'
    } else {
        try {
            $state = Read-And-ValidateRuntime $runtime
            if ($ApplicationReadOnly) { Invoke-AnonymousApplicationReadOnly } else { Invoke-HttpPreflight }
        } catch {
            Add-Failure $_.Exception.Message
        }
    }
} catch {
    Add-Failure 'No se pudo crear el proceso lanzador.'
} finally {
    if ($null -ne $runtime -and (Test-Path -LiteralPath (Join-Path $runtime 'process.json') -PathType Leaf)) {
        try {
            if ($null -eq $state) {
                $state = Get-Content -LiteralPath (Join-Path $runtime 'process.json') -Raw | ConvertFrom-Json
            }
            try {
                $cleanupStopOutput = @(& $stopScript -Runtime $runtime)
                $stopOutputText = ($cleanupStopOutput | ForEach-Object { [string]$_ }) -join ' '
            } catch {
                $stopOutputText = Sanitize-Diagnostic $_.Exception.Message
            }
            $stopOutputPass = $stopOutputText -match 'PROCESS_STATUS=(STOPPED_CLEAN|ALREADY_EXITED_VERIFIED|ALREADY_EXITED_CLEAN)'
            if ($stopOutputPass) {
                $stopObserved = 'VERIFIED_CLEAN_STOP'
                $stopResult = 'PASS'
            } else {
                if (Test-IndependentRuntimeCleanup $state) {
                    $stopObserved = 'INDEPENDENTLY_VERIFIED_ALREADY_CLEAN'
                    $stopResult = 'PASS'
                    $stopOutputPass = $true
                } else {
                    $stopObserved = Sanitize-Diagnostic $stopOutputText
                    $stopResult = 'FAIL'
                }
            }
            Add-CleanupCheck 'APACHE_STOP_SCRIPT' 'owned Apache runtime' 'verified clean stop' $stopObserved $stopResult 'stop-m07-apache.ps1 output'
            if (-not $stopOutputPass) {
                throw ('El script de detención no devolvió un estado limpio verificable: ' + (Sanitize-Diagnostic $stopOutputText))
            }
            $remainingProcess = Get-Process -Id ([int]$state.pid) -ErrorAction SilentlyContinue
            $remainingListener = Get-NetTCPConnection -State Listen -LocalAddress '127.0.0.1' -LocalPort $Port -ErrorAction SilentlyContinue | Select-Object -First 1
            $remainingSyntheticProcesses = @(Get-SyntheticProcessRecordsForState $state)
            if ($null -eq $remainingProcess) {
                $processObserved = 'NO_OWNED_ROOT_PROCESS'
                $processResult = 'PASS'
            } else {
                $processObserved = 'OWNED_ROOT_PROCESS_ALIVE'
                $processResult = 'FAIL'
            }
            if ($null -eq $remainingListener) {
                $listenerObserved = 'FREE'
                $listenerResult = 'PASS'
            } else {
                $listenerObserved = 'LISTENER_OCCUPIED'
                $listenerResult = 'FAIL'
            }
            Add-CleanupCheck 'APACHE_PROCESS_EXIT' 'recorded Apache PID' 'NO_OWNED_PROCESS_ALIVE' $processObserved $processResult ('process.json pid=' + [string]$state.pid)
            $treeObserved = if ($remainingSyntheticProcesses.Count -eq 0) { 'NO_RUNTIME_PROCESSES' } else { 'RUNTIME_PROCESS_REMAINS' }
            $treeResult = if ($remainingSyntheticProcesses.Count -eq 0) { 'PASS' } else { 'FAIL' }
            Add-CleanupCheck 'APACHE_PROCESS_TREE_EXIT' 'process.json executable and config' 'NO_RUNTIME_PROCESSES' $treeObserved $treeResult 'Win32_Process executable/config identity'
            Add-CleanupCheck 'PORT_8117_RELEASE' '127.0.0.1:8117' 'FREE' $listenerObserved $listenerResult 'Get-NetTCPConnection'
            if ($null -eq $remainingProcess -and $null -eq $remainingListener -and $remainingSyntheticProcesses.Count -eq 0) {
                $cleanupStatus = 'PASS'
            } else {
                $cleanupStatus = 'FAIL'
                $cleanupFailureDetail = 'POST_STOP_RESOURCE_CHECK_FAILED'
                Add-Failure 'La detención no confirmó la desaparición del proceso y listener.'
            }
        } catch {
            $cleanupStatus = 'FAIL'
            $cleanupFailureDetail = Sanitize-Diagnostic $_.Exception.Message
            Add-CleanupCheck 'CLEANUP_EXCEPTION' 'synthetic cleanup' 'no exception' $cleanupFailureDetail 'FAIL' 'finally/stop validation'
            Add-Failure ('Falló la detención segura del runtime recuperado: ' + $cleanupFailureDetail)
        }
    } elseif ($null -ne $launcher -and -not $launcher.HasExited) {
        & taskkill.exe /PID $launcher.Id /T /F >$null 2>$null
        $cleanupStatus = 'PARTIAL_NO_RUNTIME'
        $cleanupFailureDetail = 'RUNTIME_NOT_RECOVERED_LAUNCHER_TERMINATED'
        Add-CleanupCheck 'RUNTIME_RECOVERY' 'synthetic runtime' 'runtime recovered' 'NOT_RECOVERED' 'FAIL' 'launcher cleanup branch'
    }

    Remove-Item -LiteralPath $wrapperPath -Force -ErrorAction SilentlyContinue
    if ($null -eq $failure) {
        Remove-Item -LiteralPath $outputPath, $errorPath -Force -ErrorAction SilentlyContinue
        Remove-Item -LiteralPath $statusPath -Force -ErrorAction SilentlyContinue
    } else {
        $diagnosticsPreserved = 'YES'
    }
}

[ordered]@{
    PREFLIGHT_HARNESS = 'M07_APACHE_PREFLIGHT_MEDIUM'
    LAUNCHER_EXIT_CODE = $exitCodeStatus
    EXPLICIT_CHILD_RESULT = $explicitResult
    RUNTIME_RECOVERED = if ($null -ne $runtime) { 'YES' } else { 'NO' }
    HTTP_STATUS = $httpStatus
    HTTP_BODY = $httpBody
    HTTP_RESPONSE_BODY_CLASSIFICATION = $httpBodyClass
    HTTP_RESPONSE_BODY_LENGTH = $httpBodyLength
    HTTP_FAILURE_TYPE = $httpFailureType
    HTTP_RESULT = $httpResult
    HTTP_503_SOURCE = $http503Source
    HTTP_GUARD_FAILURE_CODE = $httpGuardCode
    HTTP_CASE_RESULTS = ($httpCaseResults | ConvertTo-Json -Compress)
    CLEANUP = $cleanupStatus
    CLEANUP_FAILURE_DETAIL = $cleanupFailureDetail
    CLEANUP_CHECKS = ($cleanupChecks | ConvertTo-Json -Compress)
    DIAGNOSTICS_PRESERVED = $diagnosticsPreserved
    RESULT = if ($null -eq $failure) { 'PASS' } else { 'FAIL' }
    FAILURE = if ($null -eq $failure) { 'NONE' } else { $failure }
} | ConvertTo-Json -Compress

if ($null -ne $failure) { exit 1 }
