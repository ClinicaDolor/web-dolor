[CmdletBinding()]
param([Parameter(Mandatory = $true)][string]$Runtime)

$ErrorActionPreference = 'Stop'
$runtimeBase = [IO.Path]::GetFullPath((Join-Path $env:USERPROFILE 'AppData\Local\ClinicaDolor\vosk-realtime\runs'))
$runtimePath = [IO.Path]::GetFullPath($Runtime)
if (-not $runtimePath.StartsWith($runtimeBase + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) { throw 'RUNTIME_PATH_NOT_ALLOWLISTED' }
$statePath = Join-Path $runtimePath 'process.json'
if (-not (Test-Path -LiteralPath $statePath -PathType Leaf)) { throw 'RUNTIME_STATE_MISSING' }
$state = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
$repo = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$expectedGateway = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot 'vosk_gateway.py'))
$expectedPython = [IO.Path]::GetFullPath((Join-Path (Join-Path $env:USERPROFILE 'AppData\Local\ClinicaDolor\vosk-realtime') 'venv\Scripts\python.exe'))
$pythonBaseOutput = @(& $expectedPython -c "import sys; print(getattr(sys, '_base_executable', sys.executable))")
if ($LASTEXITCODE -ne 0 -or $pythonBaseOutput.Count -eq 0) { throw 'VENV_BASE_PYTHON_UNAVAILABLE' }
$expectedPythonBase = [IO.Path]::GetFullPath([string]$pythonBaseOutput[-1].Trim())
$expectedPythonImages = @($expectedPython, $expectedPythonBase) | Select-Object -Unique
$expectedApache = [IO.Path]::GetFullPath('C:\xampp\apache\bin\httpd.exe')

function Send-IsolatedApacheCtrlC([int]$TargetProcessId, [string]$ConfigPath, [string]$ExpectedExecutable) {
    if (-not ('VoskDiagnosticApacheConsoleControl' -as [type])) {
        Add-Type -TypeDefinition @'
using System;
using System.Runtime.InteropServices;
public static class VoskDiagnosticApacheConsoleControl {
 [DllImport("kernel32.dll", SetLastError=true)] public static extern bool FreeConsole();
 [DllImport("kernel32.dll", SetLastError=true)] public static extern bool AttachConsole(uint pid);
 [DllImport("kernel32.dll", SetLastError=true)] public static extern uint GetConsoleProcessList([Out] uint[] ids, uint count);
 [DllImport("kernel32.dll", SetLastError=true)] public static extern bool SetConsoleCtrlHandler(IntPtr handler, bool add);
 [DllImport("kernel32.dll", SetLastError=true)] public static extern bool GenerateConsoleCtrlEvent(uint eventCode, uint processGroupId);
}
'@
    }
    $target = Get-CimInstance Win32_Process -Filter ("ProcessId = {0}" -f $TargetProcessId) -ErrorAction Stop
    if (-not $target -or [IO.Path]::GetFullPath([string]$target.ExecutablePath) -ne [IO.Path]::GetFullPath($ExpectedExecutable) -or
        [string]$target.CommandLine -notlike ('*' + $ConfigPath + '*')) { throw 'DIAGNOSTIC_APACHE_PROCESS_IDENTITY_MISMATCH' }
    [void][VoskDiagnosticApacheConsoleControl]::FreeConsole()
    if (-not [VoskDiagnosticApacheConsoleControl]::AttachConsole([uint32]$TargetProcessId)) { throw 'DIAGNOSTIC_APACHE_CONSOLE_ATTACH_FAILED' }
    $consoleIds = New-Object 'uint[]' 64
    $consoleCount = [VoskDiagnosticApacheConsoleControl]::GetConsoleProcessList($consoleIds, 64)
    if ($consoleCount -lt 2 -or $consoleCount -gt 64) { throw 'DIAGNOSTIC_APACHE_CONSOLE_PROCESS_SET_INVALID' }
    $members = @()
    for ($index = 0; $index -lt $consoleCount; $index++) { $members += [int]$consoleIds[$index] }
    if ($members -notcontains $PID -or $members -notcontains $TargetProcessId -or
        @($members | Where-Object { $_ -ne $PID -and $_ -ne $TargetProcessId }).Count -gt 0) { throw 'DIAGNOSTIC_APACHE_CONSOLE_HAS_UNRELATED_PROCESSES' }
    if (-not [VoskDiagnosticApacheConsoleControl]::SetConsoleCtrlHandler([IntPtr]::Zero, $true)) { throw 'DIAGNOSTIC_APACHE_CTRL_C_GUARD_FAILED' }
    if (-not [VoskDiagnosticApacheConsoleControl]::GenerateConsoleCtrlEvent(0, 0)) { throw 'DIAGNOSTIC_APACHE_GRACEFUL_CTRL_C_FAILED' }
}

if ($state.mode -eq 'normal_application_gateway') {
    if ($state.apache_owned -ne $false -or $state.database -ne 'NOT_USED' -or $state.origin -ne 'https://clinica.test' -or [int]$state.gateway_port -ne 8766 -or
        [IO.Path]::GetFullPath([string]$state.gateway_script) -ne $expectedGateway -or
        [IO.Path]::GetFullPath([string]$state.gateway_executable) -ne $expectedPython -or
        [IO.Path]::GetFullPath([string]$state.gateway_base_executable) -ne $expectedPythonBase -or
        [IO.Path]::GetFullPath([string]$state.gateway_listener_executable) -notin $expectedPythonImages) {
        throw 'NORMAL_RUNTIME_IDENTITY_MISMATCH'
    }
    $diagnosticConfigPath = Join-Path (Join-Path $env:USERPROFILE 'AppData\Local\ClinicaDolor\vosk-realtime') 'diagnostic.json'
    $disabledDiagnosticConfig = [ordered]@{ version = 1; enabled = $false; origin = 'https://clinica.test' } | ConvertTo-Json -Compress
    $disabledTempPath = $diagnosticConfigPath + '.tmp'
    Set-Content -LiteralPath $disabledTempPath -Value $disabledDiagnosticConfig -Encoding ASCII
    Move-Item -LiteralPath $disabledTempPath -Destination $diagnosticConfigPath -Force
    $listener = Get-NetTCPConnection -State Listen -LocalAddress '127.0.0.1' -LocalPort 8766 -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($listener -and [int]$listener.OwningProcess -ne [int]$state.gateway_listener_pid) { throw 'NORMAL_GATEWAY_PORT_OWNER_MISMATCH' }
    $gatewayProcessIds = @([int]$state.gateway_pid, [int]$state.gateway_listener_pid | Select-Object -Unique)
    foreach ($gatewayProcessId in $gatewayProcessIds) {
        $gatewayInfo = Get-CimInstance Win32_Process -Filter ("ProcessId = {0}" -f $gatewayProcessId) -ErrorAction SilentlyContinue
        if ($gatewayInfo) {
            if ([IO.Path]::GetFullPath([string]$gatewayInfo.ExecutablePath) -notin $expectedPythonImages -or [string]$gatewayInfo.CommandLine -notlike ('*' + $expectedGateway + '*')) { throw 'GATEWAY_PROCESS_IDENTITY_MISMATCH' }
            & taskkill.exe /PID $gatewayProcessId /T /F *> $null
        }
    }
    $deadline = [DateTime]::UtcNow.AddSeconds(10)
    do {
        $listener = Get-NetTCPConnection -State Listen -LocalAddress '127.0.0.1' -LocalPort 8766 -ErrorAction SilentlyContinue
        $remaining = @($gatewayProcessIds | ForEach-Object { Get-CimInstance Win32_Process -Filter ("ProcessId = {0}" -f [int]$_) -ErrorAction SilentlyContinue } | Where-Object { $_ })
        if (-not $listener -and $remaining.Count -eq 0) { break }
        Start-Sleep -Milliseconds 150
    } while ([DateTime]::UtcNow -lt $deadline)
    $listener = Get-NetTCPConnection -State Listen -LocalAddress '127.0.0.1' -LocalPort 8766 -ErrorAction SilentlyContinue
    $remaining = @($gatewayProcessIds | ForEach-Object { Get-CimInstance Win32_Process -Filter ("ProcessId = {0}" -f [int]$_) -ErrorAction SilentlyContinue } | Where-Object { $_ })
    $normalFailures = @()
    if ($listener) { $normalFailures += 'LOOPBACK_LISTENER_REMAINS' }
    if ($remaining.Count -gt 0) { $normalFailures += 'OWNED_GATEWAY_PROCESS_REMAINS' }
    [ordered]@{ status = if ($normalFailures.Count -eq 0) { 'CLEANUP_PASS' } else { 'CLEANUP_FAILED' }; apache_touched = $false; port_8766_free = (-not $listener); owned_gateway_processes = $remaining.Count; failures = $normalFailures } | ConvertTo-Json -Compress
    if ($listener -or $remaining.Count -gt 0) { exit 1 }
    return
}

if ($state.mode -eq 'isolated_diagnostic') {
    $expectedConfig = [IO.Path]::GetFullPath((Join-Path $runtimePath 'httpd.conf'))
    $expectedShutdown = [IO.Path]::GetFullPath((Join-Path $runtimePath 'gateway.shutdown'))
    if ($state.database -ne 'NOT_USED' -or $state.diagnostic_only -ne $true -or $state.apache_owned -ne $true -or
        $state.origin -ne 'https://clinica.test:8443' -or [int]$state.apache_port -ne 8443 -or [int]$state.gateway_port -ne 8767 -or
        [IO.Path]::GetFullPath([string]$state.apache_executable) -ne $expectedApache -or
        [IO.Path]::GetFullPath([string]$state.apache_config) -ne $expectedConfig -or
        [IO.Path]::GetFullPath([string]$state.gateway_script) -ne $expectedGateway -or
        [IO.Path]::GetFullPath([string]$state.gateway_executable) -ne $expectedPython -or
        [IO.Path]::GetFullPath([string]$state.gateway_base_executable) -ne $expectedPythonBase -or
        [IO.Path]::GetFullPath([string]$state.gateway_listener_executable) -notin $expectedPythonImages -or
        [IO.Path]::GetFullPath([string]$state.gateway_shutdown_file) -ne $expectedShutdown) { throw 'DIAGNOSTIC_RUNTIME_IDENTITY_MISMATCH' }

    $diagnosticConfigPath = Join-Path (Join-Path $env:USERPROFILE 'AppData\Local\ClinicaDolor\vosk-realtime') 'diagnostic.json'
    $disabledDiagnosticConfig = [ordered]@{ version = 1; enabled = $false; mode = 'isolated_https'; origin = 'https://clinica.test:8443' } | ConvertTo-Json -Compress
    $disabledTempPath = $diagnosticConfigPath + '.tmp'
    Set-Content -LiteralPath $disabledTempPath -Value $disabledDiagnosticConfig -Encoding ASCII
    Move-Item -LiteralPath $disabledTempPath -Destination $diagnosticConfigPath -Force

    try { $listeners = @(Get-NetTCPConnection -State Listen -ErrorAction Stop | Where-Object { $_.LocalPort -in @(8443, 8767) }) }
    catch { throw 'DIAGNOSTIC_LISTENER_STATE_UNVERIFIABLE' }
    foreach ($listener in $listeners) {
        if ($listener.LocalAddress -ne '127.0.0.1') { throw 'DIAGNOSTIC_NON_LOOPBACK_LISTENER_DETECTED' }
        $expectedOwner = if ($listener.LocalPort -eq 8443) { [int]$state.apache_listener_pid } else { [int]$state.gateway_listener_pid }
        if ([int]$listener.OwningProcess -ne $expectedOwner) { throw 'DIAGNOSTIC_PORT_OWNER_MISMATCH' }
    }
    $apacheInfo = Get-CimInstance Win32_Process -Filter ("ProcessId = {0}" -f [int]$state.apache_pid) -ErrorAction Stop
    if ($apacheInfo -and ([IO.Path]::GetFullPath([string]$apacheInfo.ExecutablePath) -ne $expectedApache -or [string]$apacheInfo.CommandLine -notlike ('*' + [string]$state.apache_config + '*'))) { throw 'DIAGNOSTIC_APACHE_PROCESS_IDENTITY_MISMATCH' }
    $ownedApacheProcesses = @(Get-CimInstance Win32_Process -ErrorAction Stop | Where-Object { $_.Name -ieq 'httpd.exe' -and [string]$_.CommandLine -like ('*' + [string]$state.apache_config + '*') })
    foreach ($ownedApache in $ownedApacheProcesses) {
        if ([IO.Path]::GetFullPath([string]$ownedApache.ExecutablePath) -ne $expectedApache) { throw 'DIAGNOSTIC_APACHE_PROCESS_IDENTITY_MISMATCH' }
    }
    foreach ($gatewayProcessId in @([int]$state.gateway_pid, [int]$state.gateway_listener_pid | Select-Object -Unique)) {
        $gatewayInfo = Get-CimInstance Win32_Process -Filter ("ProcessId = {0}" -f $gatewayProcessId) -ErrorAction Stop
        if ($gatewayInfo -and ([IO.Path]::GetFullPath([string]$gatewayInfo.ExecutablePath) -notin $expectedPythonImages -or [string]$gatewayInfo.CommandLine -notlike ('*' + $expectedGateway + '*'))) { throw 'DIAGNOSTIC_GATEWAY_PROCESS_IDENTITY_MISMATCH' }
    }

    Set-Content -LiteralPath $expectedShutdown -Value 'stop' -Encoding ASCII
    if ($apacheInfo) { Send-IsolatedApacheCtrlC -TargetProcessId ([int]$state.apache_pid) -ConfigPath $expectedConfig -ExpectedExecutable $expectedApache }

    $deadline = [DateTime]::UtcNow.AddSeconds(20)
    do {
        $remainingListeners = @(Get-NetTCPConnection -State Listen -ErrorAction Stop | Where-Object { $_.LocalPort -in @(8443, 8767) })
        $remainingApache = @(Get-CimInstance Win32_Process -ErrorAction Stop | Where-Object { $_.Name -ieq 'httpd.exe' -and [string]$_.CommandLine -like ('*' + [string]$state.apache_config + '*') })
        $remainingGateway = @(@([int]$state.gateway_pid, [int]$state.gateway_listener_pid | Select-Object -Unique) | ForEach-Object { Get-CimInstance Win32_Process -Filter ("ProcessId = {0}" -f [int]$_) -ErrorAction Stop } | Where-Object { $_ })
        if ($remainingListeners.Count -eq 0 -and $remainingApache.Count -eq 0 -and $remainingGateway.Count -eq 0) { break }
        Start-Sleep -Milliseconds 200
    } while ([DateTime]::UtcNow -lt $deadline)
    $failures = @()
    if ($remainingListeners.Count -gt 0) { $failures += 'LISTENER_REMAINS' }
    if ($remainingApache.Count -gt 0) { $failures += 'OWNED_APACHE_PROCESS_REMAINS' }
    if ($remainingGateway.Count -gt 0) { $failures += 'OWNED_GATEWAY_PROCESS_REMAINS' }
    [ordered]@{ status = if ($failures.Count -eq 0) { 'CLEANUP_PASS' } else { 'CLEANUP_FAILED' }; forced_termination = $false; diagnostic_disabled = $true; ports_8443_8767_free = ($remainingListeners.Count -eq 0); normal_gateway_8766_checked_not_stopped = $true; remaining_apache = $remainingApache.Count; remaining_gateway = $remainingGateway.Count; logs_preserved = $true; failures = $failures } | ConvertTo-Json -Compress
    if ($failures.Count -gt 0) { exit 1 }
    return
}

$identityMismatch = ($state.database -ne 'clinica_dolor_demo_20261010_final') -or
    ($state.origin -ne 'https://clinica.test:8443') -or
    ([int]$state.apache_port -ne 8443) -or ([int]$state.gateway_port -ne 8766) -or
    ([IO.Path]::GetFullPath([string]$state.apache_executable) -ne $expectedApache) -or
    ([IO.Path]::GetFullPath([string]$state.gateway_executable) -ne $expectedPython) -or
    ([IO.Path]::GetFullPath([string]$state.gateway_base_executable) -ne $expectedPythonBase) -or
    ([IO.Path]::GetFullPath([string]$state.gateway_listener_executable) -notin $expectedPythonImages) -or
    ([IO.Path]::GetFullPath([string]$state.gateway_script) -ne $expectedGateway) -or
    ([IO.Path]::GetFullPath([string]$state.apache_config) -ne [IO.Path]::GetFullPath((Join-Path $runtimePath 'httpd.conf')))
if ($identityMismatch) {
    throw 'RUNTIME_IDENTITY_MISMATCH'
}

$failures = [System.Collections.Generic.List[string]]::new()
$gatewayProcessIds = @([int]$state.gateway_pid, [int]$state.gateway_listener_pid | Select-Object -Unique)
foreach ($gatewayProcessId in $gatewayProcessIds) {
    $gatewayInfo = Get-CimInstance Win32_Process -Filter ("ProcessId = {0}" -f $gatewayProcessId) -ErrorAction SilentlyContinue
    if ($gatewayInfo) {
        if ([IO.Path]::GetFullPath([string]$gatewayInfo.ExecutablePath) -notin $expectedPythonImages -or [string]$gatewayInfo.CommandLine -notlike ('*' + $expectedGateway + '*')) {
            throw 'GATEWAY_PROCESS_IDENTITY_MISMATCH'
        }
        & taskkill.exe /PID $gatewayProcessId /T /F *> $null
    }
}

$apacheInfo = Get-CimInstance Win32_Process -Filter ("ProcessId = {0}" -f [int]$state.apache_pid) -ErrorAction SilentlyContinue
if ($apacheInfo) {
    if ([IO.Path]::GetFullPath([string]$apacheInfo.ExecutablePath) -ne $expectedApache -or [string]$apacheInfo.CommandLine -notlike ('*' + [string]$state.apache_config + '*')) {
        throw 'APACHE_PROCESS_IDENTITY_MISMATCH'
    }
    & taskkill.exe /PID ([int]$state.apache_pid) /T /F *> $null
}

$deadline = [DateTime]::UtcNow.AddSeconds(10)
do {
    $listeners = @(Get-NetTCPConnection -State Listen -LocalAddress '127.0.0.1' -ErrorAction SilentlyContinue | Where-Object { $_.LocalPort -in @(8443, 8766) })
    $apacheLeft = Get-CimInstance Win32_Process -ErrorAction SilentlyContinue | Where-Object { $_.Name -ieq 'httpd.exe' -and [string]$_.CommandLine -like ('*' + [string]$state.apache_config + '*') }
    $gatewayLeft = @($gatewayProcessIds | ForEach-Object { Get-CimInstance Win32_Process -Filter ("ProcessId = {0}" -f [int]$_) -ErrorAction SilentlyContinue } | Where-Object { $_ })
    if ($listeners.Count -eq 0 -and -not $apacheLeft -and -not $gatewayLeft) { break }
    Start-Sleep -Milliseconds 150
} while ([DateTime]::UtcNow -lt $deadline)

$listeners = @(Get-NetTCPConnection -State Listen -LocalAddress '127.0.0.1' -ErrorAction SilentlyContinue | Where-Object { $_.LocalPort -in @(8443, 8766) })
$apacheLeft = Get-CimInstance Win32_Process -ErrorAction SilentlyContinue | Where-Object { $_.Name -ieq 'httpd.exe' -and [string]$_.CommandLine -like ('*' + [string]$state.apache_config + '*') }
foreach ($residual in @($apacheLeft)) {
    if ([IO.Path]::GetFullPath([string]$residual.ExecutablePath) -ne $expectedApache) { throw 'RESIDUAL_APACHE_IDENTITY_MISMATCH' }
    & taskkill.exe /PID ([int]$residual.ProcessId) /T /F *> $null
}
if ($apacheLeft) { Start-Sleep -Milliseconds 250 }
$apacheLeft = Get-CimInstance Win32_Process -ErrorAction SilentlyContinue | Where-Object { $_.Name -ieq 'httpd.exe' -and [string]$_.CommandLine -like ('*' + [string]$state.apache_config + '*') }
$listeners = @(Get-NetTCPConnection -State Listen -LocalAddress '127.0.0.1' -ErrorAction SilentlyContinue | Where-Object { $_.LocalPort -in @(8443, 8766) })
$gatewayLeft = @($gatewayProcessIds | ForEach-Object { Get-CimInstance Win32_Process -Filter ("ProcessId = {0}" -f [int]$_) -ErrorAction SilentlyContinue } | Where-Object { $_ })
if ($listeners.Count -gt 0) { $failures.Add('LOOPBACK_LISTENER_REMAINS') }
if ($apacheLeft) { $failures.Add('OWNED_APACHE_PROCESS_REMAINS') }
if ($gatewayLeft) { $failures.Add('OWNED_GATEWAY_PROCESS_REMAINS') }

[ordered]@{
    status = if ($failures.Count -eq 0) { 'CLEANUP_PASS' } else { 'CLEANUP_FAILED' }
    ports_8443_8766_free = ($listeners.Count -eq 0)
    owned_apache_processes = @($apacheLeft).Count
    owned_gateway_processes = $gatewayLeft.Count
    diagnostics_preserved = $true
    failures = @($failures)
} | ConvertTo-Json -Compress
if ($failures.Count -gt 0) { exit 1 }
