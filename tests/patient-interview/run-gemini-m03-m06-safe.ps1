[CmdletBinding()]
param(
    [ValidateSet('Preflight', 'Real')]
    [string]$Mode = 'Preflight',
    [string]$PhpPath = 'C:\xampp\php\php.exe',
    [int]$Port = 0,
    [switch]$AllowRealCalls,
    [string]$SyntheticConfirmation = ''
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$harness = Join-Path $PSScriptRoot 'gemini-m03-m06-http.php'
$syntheticDatabase = 'clinica_dolor_browser_20261012'
$syntheticPatient = 9302

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

$versionRun = Invoke-PHPProcess @('-r', 'echo PHP_VERSION, chr(124), PHP_SAPI, chr(124), PHP_BINARY;')
if ($versionRun.ExitCode -ne 0) {
    throw 'PHP_XAMPP_NOT_EXECUTABLE'
}
$phpVersion = $versionRun.Stdout.Trim()

if ($Mode -eq 'Real' -and (-not $AllowRealCalls -or $SyntheticConfirmation -ne 'I_CONFIRM_SYNTHETIC_9302_TWO_CALLS')) {
    throw 'REAL_CALLS_BLOCKED_EXPLICIT_CONFIRMATION_REQUIRED'
}

function Invoke-SyntheticProbe {
    $probeRun = Invoke-PHPProcess @($harness, '--probe')
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
            patient_9302_guard = [int]$probe.patient_synthetic -eq $syntheticPatient
            application_external_disabled = $true
            real_gemini_calls = 0
        } | ConvertTo-Json -Compress
        $skipReal = $true
    }

    if (-not $skipReal) {
        # The existing harness performs exactly one M03 and one M06 request. Its
        # verify-only mode does not call confirmation endpoints or write clinical data.
        $realRun = Invoke-PHPProcess @($harness, '--verify-only')
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
