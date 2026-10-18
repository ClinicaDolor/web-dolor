[CmdletBinding()]
param(
    [ValidateSet('Preflight', 'Real', 'Diagnostics')]
    [string]$Mode = 'Preflight',
    [string]$PhpPath = 'C:\xampp\php\php.exe',
    [int]$Port = 0,
    [ValidateSet('clinica_dolor_browser_20261012', 'clinica_dolor_demo_20261010_final')]
    [string]$SyntheticDatabase = 'clinica_dolor_demo_20261010_final',
    [ValidateSet(9301, 9302)]
    [int]$SyntheticPatient = 9301,
    [switch]$IncludeM02,
    [switch]$AllowRealCalls,
    [string]$SyntheticConfirmation = ''
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$harness = Join-Path $PSScriptRoot 'gemini-m03-m06-http.php'
$syntheticDatabase = $SyntheticDatabase
$syntheticPatient = $SyntheticPatient
$expectedConfirmation = if ($IncludeM02) { 'I_CONFIRM_SYNTHETIC_DELIVERY16_THREE_CALLS' } else { 'I_CONFIRM_SYNTHETIC_9302_TWO_CALLS' }

if (-not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) {
    throw 'PHP_XAMPP_NOT_FOUND'
}
if (-not (Test-Path -LiteralPath $harness -PathType Leaf)) {
    throw 'SYNTHETIC_HARNESS_NOT_FOUND'
}

function Get-DotEnvValue([string]$Name) {
    $envPath = Join-Path $projectRoot '.env'
    foreach ($line in (Get-Content -LiteralPath $envPath -ErrorAction Stop)) {
        if ($line -match ('^\s*' + [regex]::Escape($Name) + '\s*=\s*(.*)\s*$')) {
            return $Matches[1].Trim().Trim('"').Trim("'")
        }
    }
    return ''
}

function Find-FreeLoopbackPort([int]$RequestedPort) {
    if ($RequestedPort -gt 0) {
        $busy = Test-NetConnection -ComputerName 127.0.0.1 -Port $RequestedPort -InformationLevel Quiet -WarningAction SilentlyContinue
        if ($busy) { throw 'SYNTHETIC_HTTP_PORT_OCCUPIED' }
        return $RequestedPort
    }

    $listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, 0)
    try {
        $listener.Start()
        return ([System.Net.IPEndPoint]$listener.LocalEndpoint).Port
    } finally {
        $listener.Stop()
    }
}

function Stop-OwnHttpListener([int]$LocalPort) {
    $lines = netstat.exe -ano -p tcp 2>$null | Select-String (':' + $LocalPort + '\s+.*LISTENING\s+')
    foreach ($line in $lines) {
        $parts = $line.ToString().Trim() -split '\s+'
        $listenerPid = [int]$parts[-1]
        $process = Get-Process -Id $listenerPid -ErrorAction SilentlyContinue
        if ($process -and $process.Path -ieq $PhpPath) {
            Stop-Process -Id $listenerPid -Force -ErrorAction SilentlyContinue
        }
    }
}

# The shared application must remain externally disabled. This reads only a
# boolean flag; it never prints or forwards credentials.
if ((Get-DotEnvValue 'CLINICAL_AI_EXTERNAL_ENABLED') -eq '1') {
    throw 'HABITUAL_EXTERNAL_GEMINI_MUST_BE_DISABLED'
}

function Invoke-PHPProcess([string[]]$Arguments) {
    $outPath = [IO.Path]::GetTempFileName()
    $errPath = [IO.Path]::GetTempFileName()
    try {
        $argumentText = ($Arguments | ForEach-Object { '"' + $_.Replace('"', '\"') + '"' }) -join ' '
        $process = Start-Process -FilePath $PhpPath -ArgumentList $argumentText -WindowStyle Hidden -Wait -PassThru -RedirectStandardOutput $outPath -RedirectStandardError $errPath
        [pscustomobject]@{
            ExitCode = $process.ExitCode
            Stdout = (Get-Content -LiteralPath $outPath -Raw -ErrorAction SilentlyContinue)
            Stderr = (Get-Content -LiteralPath $errPath -Raw -ErrorAction SilentlyContinue)
        }
    } finally {
        Remove-Item -LiteralPath $outPath,$errPath -Force -ErrorAction SilentlyContinue
    }
}

function Get-SafeProcessDiagnostic($Run) {
    $sha = [System.Security.Cryptography.SHA256]::Create()
    try {
        $stdout = if ($null -eq $Run.Stdout) { '' } else { [string]$Run.Stdout }
        $stderr = if ($null -eq $Run.Stderr) { '' } else { [string]$Run.Stderr }
        $stdoutHash = ([BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($stdout)))).Replace('-', '').ToLowerInvariant()
        $stderrHash = ([BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($stderr)))).Replace('-', '').ToLowerInvariant()
    } finally { $sha.Dispose() }
    $jsonValid = $false
    if (-not [string]::IsNullOrWhiteSpace($stdout)) { try { $null = $stdout | ConvertFrom-Json -ErrorAction Stop; $jsonValid = $true } catch {} }
    [pscustomobject]@{
        exit_code = [int]$Run.ExitCode
        stdout_present = -not [string]::IsNullOrEmpty($stdout)
        stdout_bytes = [Text.Encoding]::UTF8.GetByteCount($stdout)
        stdout_sha256 = $stdoutHash
        stdout_json_valid = $jsonValid
        stderr_present = -not [string]::IsNullOrEmpty($stderr)
        stderr_bytes = [Text.Encoding]::UTF8.GetByteCount($stderr)
        stderr_sha256 = $stderrHash
    }
}

$versionRun = Invoke-PHPProcess @('-r', 'echo PHP_VERSION, chr(124), PHP_SAPI, chr(124), PHP_BINARY;')
if ($versionRun.ExitCode -ne 0) {
    throw 'PHP_XAMPP_NOT_EXECUTABLE'
}
$phpVersion = $versionRun.Stdout.Trim()

if ($Mode -eq 'Diagnostics') {
    $scenarios = @(
        @{ name = 'json'; args = @('-r', "echo json_encode(array('status'=>'ok'));") ; expectedExit = 0; expectedJson = $true },
        @{ name = 'empty'; args = @('-r', 'exit(0);') ; expectedExit = 0; expectedJson = $false },
        @{ name = 'invalid_json'; args = @('-r', "echo 'not-json';") ; expectedExit = 0; expectedJson = $false },
        @{ name = 'exception'; args = @('-r', "fwrite(STDERR, 'SIMULATED_EXCEPTION'); exit(7);") ; expectedExit = 7; expectedJson = $false },
        @{ name = 'stderr_and_json'; args = @('-r', "fwrite(STDERR, 'SIMULATED_STDERR'); echo json_encode(array('status'=>'ok'));") ; expectedExit = 0; expectedJson = $true },
        @{ name = 'premature'; args = @('-r', "fwrite(STDERR, 'SIMULATED_PREMATURE'); exit(9);") ; expectedExit = 9; expectedJson = $false }
    )
    $records = foreach ($scenario in $scenarios) {
        $run = Invoke-PHPProcess $scenario.args
        $diag = Get-SafeProcessDiagnostic $run
        [pscustomobject]@{
            name = $scenario.name
            pass = $diag.exit_code -eq $scenario.expectedExit -and $diag.stdout_json_valid -eq $scenario.expectedJson
            diagnostic = $diag
        }
    }
    [pscustomobject]@{ status = if (@($records | Where-Object { -not $_.pass }).Count -eq 0) { 'DIAGNOSTICS_PASS' } else { 'DIAGNOSTICS_FAIL' }; php_context = [string]$phpVersion; real_gemini_calls = 0; scenarios = $records } | ConvertTo-Json -Depth 8 -Compress
    return
}

if ($Mode -eq 'Real' -and (-not $AllowRealCalls -or $SyntheticConfirmation -ne $expectedConfirmation)) {
    throw 'REAL_CALLS_BLOCKED_EXPLICIT_CONFIRMATION_REQUIRED'
}

function Invoke-SyntheticProbe {
    $probeArgs = @($harness, '--probe', ('--database=' + $syntheticDatabase), ('--patient=' + $syntheticPatient))
    if ($IncludeM02) { $probeArgs += '--include-m02' }
    $probeRun = Invoke-PHPProcess $probeArgs
    if ($probeRun.ExitCode -ne 0) {
        throw 'SYNTHETIC_PREFLIGHT_FAILED'
    }

    try {
        $probe = $probeRun.Stdout | ConvertFrom-Json -ErrorAction Stop
    } catch {
        throw 'SYNTHETIC_PREFLIGHT_OUTPUT_INVALID'
    }

    if ($probe.status -ne 'PROBE_PASS' -or
        $probe.database -ne $syntheticDatabase -or
        [int]$probe.patient_synthetic -ne $syntheticPatient -or
        $probe.http_environment.effective_database -ne $syntheticDatabase -or
        $probe.http_environment.configured_database -ne $syntheticDatabase -or
        $probe.http_environment.external_enabled -ne '1' -or
        $probe.http_environment.synthetic_mode -ne '1' -or
        $probe.http_environment.gemini_key_present -ne $true) {
        throw 'SYNTHETIC_PREFLIGHT_GUARD_FAILED'
    }

    return $probe
}

$testPort = Find-FreeLoopbackPort $Port
$previousPort = [Environment]::GetEnvironmentVariable('GEMINI_HTTP_TEST_PORT', 'Process')
[Environment]::SetEnvironmentVariable('GEMINI_HTTP_TEST_PORT', [string]$testPort, 'Process')
try {
    $probe = Invoke-SyntheticProbe

    if ($Mode -eq 'Preflight') {
        [pscustomobject]@{
            status = 'READY_NO_REAL_CALLS'
            php_context = [string]$phpVersion
            synthetic_port = $testPort
            synthetic_database_guard = $probe.http_environment.effective_database -eq $syntheticDatabase
            synthetic_patient_guard = [int]$probe.patient_synthetic -eq $syntheticPatient
            application_external_disabled = $true
            real_gemini_calls = 0
        } | ConvertTo-Json -Compress
        $skipReal = $true
    }

    if (-not $skipReal) {
        # The existing harness performs exactly one M03 and one M06 request. Its
        # verify-only mode does not call confirmation endpoints or write clinical data.
        $realArgs = @($harness, '--verify-only', ('--database=' + $syntheticDatabase), ('--patient=' + $syntheticPatient))
        if ($IncludeM02) { $realArgs += '--include-m02' }
        $realRun = Invoke-PHPProcess $realArgs
        if ($realRun.ExitCode -ne 0) {
            throw 'SYNTHETIC_REAL_RUN_FAILED'
        }
        $realRun.Stdout
    }
} finally {
    Stop-OwnHttpListener $testPort
    if ($null -eq $previousPort) {
        [Environment]::SetEnvironmentVariable('GEMINI_HTTP_TEST_PORT', $null, 'Process')
    } else {
        [Environment]::SetEnvironmentVariable('GEMINI_HTTP_TEST_PORT', $previousPort, 'Process')
    }
}
