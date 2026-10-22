[CmdletBinding()]
param(
    [string]$ApacheExe = 'C:\xampp\apache\bin\httpd.exe',
    [string]$PythonExe = '',
    [switch]$NormalApplication,
    [switch]$EnableDiagnosticPage,
    [switch]$DiagnosticIsolated
)

$ErrorActionPreference = 'Stop'
$repo = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
if ($repo -ne 'C:\xampp\htdocs\clinica-dolor') { throw 'WORKSPACE_NOT_ALLOWLISTED' }
$syntheticDatabase = 'clinica_dolor_demo_20261010_final'
$expectedOrigin = 'https://clinica.test:8443'
$runtimeRoot = Join-Path $env:USERPROFILE 'AppData\Local\ClinicaDolor\vosk-realtime'
$diagnosticConfigPath = Join-Path $runtimeRoot 'diagnostic.json'
if ($DiagnosticIsolated -and ($NormalApplication -or $EnableDiagnosticPage)) { throw 'DIAGNOSTIC_ISOLATED_MODE_FLAGS_INVALID' }
if ($EnableDiagnosticPage) { throw 'DIAGNOSTIC_PAGE_REQUIRES_ISOLATED_APACHE_MODE' }
$modelPath = Join-Path $runtimeRoot 'models\vosk-model-small-es-0.42'
$ticketKey = Join-Path $runtimeRoot 'ticket.key'
$certificate = Join-Path $runtimeRoot 'tls\clinica-test-local.pem'
$privateKey = Join-Path $runtimeRoot 'tls\clinica-test-local-key.pem'
$templatePath = Join-Path $PSScriptRoot 'https-vosk-apache.conf'
$diagnosticTemplatePath = Join-Path $PSScriptRoot 'diagnostic-isolated-apache.conf'
$guardPath = Join-Path (Join-Path $PSScriptRoot '..\m07-http-isolated') 'web-database-preflight.php'
$venvPython = Join-Path $runtimeRoot 'venv\Scripts\python.exe'
if (-not $PythonExe) { $PythonExe = $venvPython }
$gatewayScript = Join-Path $PSScriptRoot 'vosk_gateway.py'

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

foreach ($required in @($PythonExe, $modelPath, $ticketKey, $certificate, $privateKey, $gatewayScript)) {
    if (-not (Test-Path -LiteralPath $required)) { throw 'LOCAL_RUNTIME_COMPONENT_MISSING' }
}
if (-not $NormalApplication) {
    $apacheRequirements = if ($DiagnosticIsolated) { @($ApacheExe, $diagnosticTemplatePath) } else { @($ApacheExe, $templatePath, $guardPath) }
    foreach ($required in $apacheRequirements) {
        if (-not (Test-Path -LiteralPath $required)) { throw 'LOCAL_RUNTIME_COMPONENT_MISSING' }
    }
}
$pythonBaseOutput = @(& $PythonExe -c "import sys; print(getattr(sys, '_base_executable', sys.executable))")
if ($LASTEXITCODE -ne 0 -or $pythonBaseOutput.Count -eq 0) { throw 'VENV_BASE_PYTHON_UNAVAILABLE' }
$pythonBaseExecutable = [IO.Path]::GetFullPath([string]$pythonBaseOutput[-1].Trim())
if (-not (Test-Path -LiteralPath $pythonBaseExecutable -PathType Leaf)) { throw 'VENV_BASE_PYTHON_UNAVAILABLE' }
$pythonExecutableAllowlist = @([IO.Path]::GetFullPath($PythonExe), $pythonBaseExecutable) | Select-Object -Unique
if ((Get-Item -LiteralPath $ticketKey).Length -ne 32) { throw 'LOCAL_RUNTIME_KEY_INVALID' }
if (-not (Test-Path -LiteralPath (Join-Path $runtimeRoot 'models\vosk-model-small-es-0.42\am\final.mdl'))) { throw 'VOSK_MODEL_INCOMPLETE' }

$hostsPath = Join-Path $env:SystemRoot 'System32\drivers\etc\hosts'
$hostMapsToLoopback = Select-String -LiteralPath $hostsPath -Pattern '^\s*127\.0\.0\.1\s+clinica\.test(?:\s|$)' -Quiet
if (-not $hostMapsToLoopback) { throw 'CLINICA_TEST_HOST_NOT_LOOPBACK' }
if ($DiagnosticIsolated) {
    $diagnosticOrigin = 'https://clinica.test:8443'
    $diagnosticGatewayPort = 8767
    $approvedCertificateSha256 = '7C:6B:0F:09:1A:91:55:9D:0F:FA:6F:4E:5F:5F:8D:97:A6:2A:0D:4B:4B:F7:0E:28:D0:74:CD:ED:59:54:D4:0D'
    $certObject = [System.Security.Cryptography.X509Certificates.X509Certificate2]::new($certificate)
    $sha256 = [System.Security.Cryptography.SHA256]::Create()
    $certificateDigest = ([BitConverter]::ToString($sha256.ComputeHash($certObject.RawData))).Replace('-', ':')
    $basicConstraints = $certObject.Extensions | Where-Object { $_.Oid.Value -eq '2.5.29.19' }
    $serverAuth = $certObject.Extensions | Where-Object { $_.Oid.Value -eq '2.5.29.37' }
    if ($certificateDigest -ne $approvedCertificateSha256 -or $certObject.Subject -ne 'CN=clinica.test' -or $certObject.Issuer -ne 'CN=clinica.test' -or
        -not (($certObject.Extensions | Where-Object { $_.Oid.Value -eq '2.5.29.17' }).Format($false).Contains('clinica.test')) -or
        -not $basicConstraints -or $basicConstraints.CertificateAuthority -ne $false -or
        -not $serverAuth -or $serverAuth.Format($false) -notmatch '1\.3\.6\.1\.5\.5\.7\.3\.1' -or
        $certObject.NotBefore -gt [DateTime]::Now -or $certObject.NotAfter -le [DateTime]::Now) { throw 'AUTHORIZED_CERTIFICATE_VALIDATION_FAILED' }
    $trustedCertificate = Get-ChildItem Cert:\CurrentUser\Root | Where-Object { $_.Thumbprint -eq $certObject.Thumbprint } | Select-Object -First 1
    if (-not $trustedCertificate) { throw 'AUTHORIZED_CERTIFICATE_NOT_TRUSTED_IN_CURRENT_USER_ROOT' }
    $chain = [System.Security.Cryptography.X509Certificates.X509Chain]::new()
    $chain.ChainPolicy.RevocationMode = [System.Security.Cryptography.X509Certificates.X509RevocationMode]::NoCheck
    if (-not $chain.Build($certObject)) { throw 'AUTHORIZED_CERTIFICATE_CHAIN_NOT_TRUSTED' }
    $resolvedHostAddresses = @([Net.Dns]::GetHostAddresses('clinica.test'))
    $loopbackV4 = [Net.IPAddress]::Parse('127.0.0.1')
    if ($resolvedHostAddresses.Count -eq 0 -or -not ($resolvedHostAddresses | Where-Object { $_.Equals($loopbackV4) }) -or @($resolvedHostAddresses | Where-Object { -not [Net.IPAddress]::IsLoopback($_) }).Count -gt 0) {
        throw 'CLINICA_TEST_NAME_NOT_LOOPBACK_ONLY'
    }
    try { $occupiedPorts = @(Get-NetTCPConnection -State Listen -ErrorAction Stop | Where-Object { $_.LocalPort -in @(8443, $diagnosticGatewayPort) }) }
    catch { throw 'DIAGNOSTIC_PORT_OWNERSHIP_UNVERIFIABLE' }
    if ($occupiedPorts.Count -gt 0) { throw 'DIAGNOSTIC_PORT_ALREADY_IN_USE' }
    if (Test-Path -LiteralPath $diagnosticConfigPath -PathType Leaf) {
        $existingConfig = Get-Content -LiteralPath $diagnosticConfigPath -Raw | ConvertFrom-Json
        if ($existingConfig.enabled -eq $true) { throw 'DIAGNOSTIC_ALREADY_ENABLED' }
    }

    $runRoot = Join-Path (Join-Path $runtimeRoot 'runs') ([Guid]::NewGuid().ToString('N'))
    $sessions = Join-Path $runRoot 'sessions'
    $tmp = Join-Path $runRoot 'tmp'
    $shutdownFile = Join-Path $runRoot 'gateway.shutdown'
    New-Item -ItemType Directory -Force -Path $sessions, $tmp | Out-Null
    $apacheConfig = Join-Path $runRoot 'httpd.conf'
    $resolved = (Get-Content -LiteralPath $diagnosticTemplatePath -Raw).
        Replace('__VOSK_RUNTIME__', $runRoot.Replace('\', '/')).
        Replace('__VOSK_CERT__', $certificate.Replace('\', '/')).
        Replace('__VOSK_CERT_KEY__', $privateKey.Replace('\', '/'))
    if ($resolved -match '(?im)^\s*Include(?:Optional)?\s' -or $resolved -match '(?im)^\s*Listen\s+(?!127\.0\.0\.1:8443\s*$)' -or
        $resolved -notmatch '(?im)^\s*Listen\s+127\.0\.0\.1:8443\s*$' -or $resolved -match 'auto_prepend_file|M07_HTTP_ISOLATED|DB_DATABASE') {
        throw 'DIAGNOSTIC_APACHE_CONFIG_SCOPE_INVALID'
    }
    Set-Content -LiteralPath $apacheConfig -Value $resolved -Encoding UTF8

    $configStart = New-Object System.Diagnostics.ProcessStartInfo
    $configStart.FileName = $ApacheExe
    $configStart.Arguments = '-f "' + $apacheConfig + '" -d "C:\xampp\apache" -t -D DUMP_VHOSTS'
    $configStart.WorkingDirectory = 'C:\xampp\apache'
    $configStart.UseShellExecute = $false
    $configStart.CreateNoWindow = $true
    $configStart.RedirectStandardOutput = $true
    $configStart.RedirectStandardError = $true
    $configTest = New-Object System.Diagnostics.Process
    $configTest.StartInfo = $configStart
    if (-not $configTest.Start()) { throw 'DIAGNOSTIC_APACHE_CONFIG_TEST_START_FAILED' }
    $configOut = $configTest.StandardOutput.ReadToEndAsync()
    $configErr = $configTest.StandardError.ReadToEndAsync()
    if (-not $configTest.WaitForExit(10000)) { $configTest.Kill(); $configTest.WaitForExit(); throw 'DIAGNOSTIC_APACHE_CONFIG_TIMEOUT' }
    $configOutput = $configOut.Result + "`n" + $configErr.Result
    $effectiveVhostLines = @([regex]::Matches($configOutput, '(?m)^\s*\S+:\d+\s+\S+\s+\([^\r\n]+\)\s*$'))
    $expectedVhostPresent = $configOutput -match '(?m)^\s*127\.0\.0\.1:8443\s+clinica\.test\s+\('
    if ($configTest.ExitCode -ne 0 -or $configOutput -notmatch 'VirtualHost configuration:' -or
        $effectiveVhostLines.Count -ne 1 -or -not $expectedVhostPresent) {
        throw 'DIAGNOSTIC_APACHE_EFFECTIVE_CONFIG_INVALID'
    }

    $sensitiveNames = @([Environment]::GetEnvironmentVariables('Process').Keys | Where-Object { $_ -match '^(DB_|DATABASE_|MYSQL_|APP_KEY$|GEMINI_|OPENAI_|CLINICAL_AI_|M07_)' })
    $savedEnvironment = @{}
    $apacheProcess = $null
    $gatewayProcess = $null
    $apacheListenerPid = 0
    $gatewayListenerPid = 0
    $configWritten = $false
    try {
        $apacheArgs = '-f "' + $apacheConfig + '" -d "C:\xampp\apache"'
        foreach ($name in $sensitiveNames) { $savedEnvironment[$name] = [Environment]::GetEnvironmentVariable([string]$name, 'Process'); [Environment]::SetEnvironmentVariable([string]$name, $null, 'Process') }
        try {
            $apacheProcess = Start-Process -FilePath $ApacheExe -ArgumentList $apacheArgs -WorkingDirectory 'C:\xampp\apache' -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runRoot 'apache-stdout.log') -RedirectStandardError (Join-Path $runRoot 'apache-stderr.log') -PassThru
        } finally { foreach ($name in $sensitiveNames) { [Environment]::SetEnvironmentVariable([string]$name, $savedEnvironment[$name], 'Process') } }

        $deadline = [DateTime]::UtcNow.AddSeconds(15)
        $apacheListener = $null
        while ([DateTime]::UtcNow -lt $deadline) {
            $apacheProcess.Refresh()
            if ($apacheProcess.HasExited) { throw 'DIAGNOSTIC_APACHE_EXITED_EARLY' }
            $apacheListener = Get-NetTCPConnection -State Listen -ErrorAction Stop | Where-Object { $_.LocalPort -eq 8443 } | Select-Object -First 1
            if ($apacheListener) { break }
            Start-Sleep -Milliseconds 150
        }
        if (-not $apacheListener -or $apacheListener.LocalAddress -ne '127.0.0.1' -or [int]$apacheListener.OwningProcess -ne $apacheProcess.Id) { throw 'DIAGNOSTIC_APACHE_LISTENER_IDENTITY_FAILED' }
        $apacheListenerPid = [int]$apacheListener.OwningProcess
        $apacheInfo = Get-CimInstance Win32_Process -Filter ("ProcessId = {0}" -f $apacheListenerPid) -ErrorAction Stop
        if (-not $apacheInfo -or [IO.Path]::GetFullPath([string]$apacheInfo.ExecutablePath) -ne [IO.Path]::GetFullPath($ApacheExe) -or [string]$apacheInfo.CommandLine -notlike ('*' + $apacheConfig + '*')) { throw 'DIAGNOSTIC_APACHE_PROCESS_IDENTITY_FAILED' }
        $httpsRequest = [Net.HttpWebRequest]::Create($diagnosticOrigin + '/assets/css/local-voice-diagnostic.css')
        $httpsRequest.Method = 'GET'; $httpsRequest.AllowAutoRedirect = $false; $httpsRequest.Timeout = 8000; $httpsRequest.Proxy = $null
        $httpsResponse = $null
        try { $httpsResponse = [Net.HttpWebResponse]$httpsRequest.GetResponse() } catch [Net.WebException] { $httpsResponse = $_.Exception.Response }
        if (-not $httpsResponse) { throw 'DIAGNOSTIC_HTTPS_PROBE_FAILED' }
        try { if ([int]$httpsResponse.StatusCode -ne 200) { throw 'DIAGNOSTIC_HTTPS_STATIC_PROBE_FAILED' } } finally { $httpsResponse.Close() }

        $gatewayArgs = @('-u', $gatewayScript, '--host', '127.0.0.1', '--port', [string]$diagnosticGatewayPort, '--origin', $diagnosticOrigin, '--model', $modelPath, '--ticket-key', $ticketKey, '--cert', $certificate, '--private-key', $privateKey, '--diagnostic-only', '--shutdown-file', $shutdownFile)
        foreach ($name in $sensitiveNames) { $savedEnvironment[$name] = [Environment]::GetEnvironmentVariable([string]$name, 'Process'); [Environment]::SetEnvironmentVariable([string]$name, $null, 'Process') }
        try { $gatewayProcess = Start-Process -FilePath $PythonExe -ArgumentList (($gatewayArgs | ForEach-Object { if ($_ -match '\s') { '"' + $_ + '"' } else { $_ } }) -join ' ') -WorkingDirectory $runtimeRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runRoot 'gateway-stdout.log') -RedirectStandardError (Join-Path $runRoot 'gateway-stderr.log') -PassThru }
        finally { foreach ($name in $sensitiveNames) { [Environment]::SetEnvironmentVariable([string]$name, $savedEnvironment[$name], 'Process') } }

        $deadline = [DateTime]::UtcNow.AddSeconds(30)
        $gatewayListener = $null
        while ([DateTime]::UtcNow -lt $deadline) {
            $gatewayProcess.Refresh()
            if ($gatewayProcess.HasExited) { throw 'DIAGNOSTIC_GATEWAY_EXITED_EARLY' }
            $gatewayListener = Get-NetTCPConnection -State Listen -ErrorAction Stop | Where-Object { $_.LocalPort -eq $diagnosticGatewayPort } | Select-Object -First 1
            $readyLog = if (Test-Path (Join-Path $runRoot 'gateway-stdout.log')) { Get-Content -LiteralPath (Join-Path $runRoot 'gateway-stdout.log') -Raw } else { '' }
            if ($gatewayListener -and $readyLog -match 'VOSK_GATEWAY_READY loopback-only tls origin-allowlisted') { break }
            Start-Sleep -Milliseconds 200
        }
        if (-not $gatewayListener -or $gatewayListener.LocalAddress -ne '127.0.0.1') { throw 'DIAGNOSTIC_GATEWAY_LISTENER_IDENTITY_FAILED' }
        $gatewayListenerPid = [int]$gatewayListener.OwningProcess
        $gatewayInfo = Get-CimInstance Win32_Process -Filter ("ProcessId = {0}" -f $gatewayListenerPid) -ErrorAction Stop
        if (-not $gatewayInfo -or [IO.Path]::GetFullPath([string]$gatewayInfo.ExecutablePath) -notin $pythonExecutableAllowlist -or [string]$gatewayInfo.CommandLine -notlike ('*' + $gatewayScript + '*')) { throw 'DIAGNOSTIC_GATEWAY_PROCESS_IDENTITY_FAILED' }

        $enabledConfig = [ordered]@{ version = 1; enabled = $true; mode = 'isolated_https'; origin = $diagnosticOrigin } | ConvertTo-Json -Compress
        $configTemp = $diagnosticConfigPath + '.tmp'
        Set-Content -LiteralPath $configTemp -Value $enabledConfig -Encoding ASCII
        Move-Item -LiteralPath $configTemp -Destination $diagnosticConfigPath -Force
        $configWritten = $true

        $pageRequest = [Net.HttpWebRequest]::Create($diagnosticOrigin + '/historia-clinica/diagnostico-voz')
        $pageRequest.Method = 'GET'; $pageRequest.AllowAutoRedirect = $false; $pageRequest.Timeout = 8000; $pageRequest.Proxy = $null
        $pageResponse = $null
        try { $pageResponse = [Net.HttpWebResponse]$pageRequest.GetResponse() } catch [Net.WebException] { $pageResponse = $_.Exception.Response }
        if (-not $pageResponse) { throw 'DIAGNOSTIC_AUTH_PREFLIGHT_TRANSPORT_FAILED' }
        try { if ([int]$pageResponse.StatusCode -ne 302 -or $pageResponse.Headers['Location'] -ne '/historia-clinica/acceso') { throw 'DIAGNOSTIC_ANONYMOUS_AUTH_GUARD_FAILED' } } finally { $pageResponse.Close() }

        $blockedRequest = [Net.HttpWebRequest]::Create($diagnosticOrigin + '/historia-clinica/entrevista/dictation-ticket')
        $blockedRequest.Method = 'POST'; $blockedRequest.AllowAutoRedirect = $false; $blockedRequest.Timeout = 5000; $blockedRequest.Proxy = $null; $blockedRequest.ContentLength = 0
        $blockedResponse = $null
        try { $blockedResponse = [Net.HttpWebResponse]$blockedRequest.GetResponse() } catch [Net.WebException] { $blockedResponse = $_.Exception.Response }
        if (-not $blockedResponse) { throw 'CLINICAL_TICKET_ROUTE_PREFLIGHT_FAILED' }
        try { if ([int]$blockedResponse.StatusCode -ne 404) { throw 'CLINICAL_TICKET_ROUTE_EXPOSED' } } finally { $blockedResponse.Close() }

        $wssProbe = @'
import asyncio,json,ssl,websockets
from websockets.exceptions import InvalidStatus
uri="wss://clinica.test:8767/diagnostic"
async def main():
    context=ssl.create_default_context()
    try:
        async with websockets.connect(uri,ssl=context,origin="https://evil.test"):
            raise RuntimeError("foreign-origin-accepted")
    except InvalidStatus as error:
        if error.response.status_code != 403: raise
    async with websockets.connect(uri,ssl=context,origin="https://clinica.test:8443") as socket:
        await socket.send(json.dumps({"type":"AUTH","ticket":"invalid"}))
        result=json.loads(await asyncio.wait_for(socket.recv(),timeout=5))
        if result != {"type":"ERROR","code":"AUTH_REJECTED"}: raise RuntimeError("invalid-ticket-not-rejected")
asyncio.run(main())
'@
        & $PythonExe -c $wssProbe
        if ($LASTEXITCODE -ne 0) { throw 'DIAGNOSTIC_WSS_SECURITY_PROBE_FAILED' }

        $state = [ordered]@{
            mode = 'isolated_diagnostic'; database = 'NOT_USED'; origin = $diagnosticOrigin; diagnostic_only = $true; apache_owned = $true
            apache_pid = [int]$apacheProcess.Id; apache_listener_pid = $apacheListenerPid; apache_executable = [IO.Path]::GetFullPath($ApacheExe); apache_config = $apacheConfig; apache_port = 8443
            gateway_pid = [int]$gatewayProcess.Id; gateway_listener_pid = $gatewayListenerPid; gateway_executable = [IO.Path]::GetFullPath($PythonExe); gateway_base_executable = $pythonBaseExecutable
            gateway_listener_executable = [IO.Path]::GetFullPath([string]$gatewayInfo.ExecutablePath); gateway_script = $gatewayScript; gateway_port = $diagnosticGatewayPort; gateway_shutdown_file = $shutdownFile
            certificate_sha256 = $certificateDigest; diagnostic_config = $diagnosticConfigPath; runtime = $runRoot; started_utc = [DateTime]::UtcNow.ToString('o')
        }
        $state | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $runRoot 'process.json') -Encoding UTF8
        [ordered]@{ status = 'STARTED'; mode = $state.mode; database = 'NOT_USED'; origin = $diagnosticOrigin; apache_pid = $state.apache_pid; gateway_pid = $state.gateway_pid; gateway_port = $diagnosticGatewayPort; runtime = $runRoot; anonymous_page_redirect = 'PASS'; clinical_ticket_route = 'BLOCKED_404'; wss_tls_and_rejections = 'PASS'; normal_gateway_8766 = 'UNTOUCHED'; m07_gate = 'CLOSED' } | ConvertTo-Json -Compress
        return
    } catch {
        if ($configWritten) {
            $disabledConfig = [ordered]@{ version = 1; enabled = $false; mode = 'isolated_https'; origin = $diagnosticOrigin } | ConvertTo-Json -Compress
            $configTemp = $diagnosticConfigPath + '.tmp'; Set-Content -LiteralPath $configTemp -Value $disabledConfig -Encoding ASCII; Move-Item -LiteralPath $configTemp -Destination $diagnosticConfigPath -Force
        }
        if ($gatewayProcess) { Set-Content -LiteralPath $shutdownFile -Value 'stop' -Encoding ASCII }
        if ($apacheProcess) {
            try { Send-IsolatedApacheCtrlC -TargetProcessId ([int]$apacheProcess.Id) -ConfigPath $apacheConfig -ExpectedExecutable $ApacheExe }
            catch { Write-Warning 'DIAGNOSTIC_APACHE_GRACEFUL_CLEANUP_SIGNAL=FAILED; process left untouched' }
        }
        $cleanupDeadline = [DateTime]::UtcNow.AddSeconds(12)
        do {
            $activePorts = @(Get-NetTCPConnection -State Listen -ErrorAction SilentlyContinue | Where-Object { $_.LocalPort -in @(8443, $diagnosticGatewayPort) })
            if ($gatewayProcess) { $gatewayProcess.Refresh() }
            if ($apacheProcess) { $apacheProcess.Refresh() }
            if ($activePorts.Count -eq 0 -and (($null -eq $gatewayProcess) -or $gatewayProcess.HasExited) -and (($null -eq $apacheProcess) -or $apacheProcess.HasExited)) { break }
            Start-Sleep -Milliseconds 200
        } while ([DateTime]::UtcNow -lt $cleanupDeadline)
        $activePorts = @(Get-NetTCPConnection -State Listen -ErrorAction Stop | Where-Object { $_.LocalPort -in @(8443, $diagnosticGatewayPort) })
        if ($activePorts.Count -gt 0) { Write-Output 'DIAGNOSTIC_START_FAILURE_CLEANUP=INCOMPLETE; runtime logs preserved' } else { Write-Output 'DIAGNOSTIC_START_FAILURE_CLEANUP=PASS; runtime logs preserved' }
        throw
    }
}
if ($NormalApplication) {
    $disabledDiagnosticConfig = [ordered]@{ version = 1; enabled = $false; origin = 'https://clinica.test' } | ConvertTo-Json -Compress
    $disabledTempPath = $diagnosticConfigPath + '.tmp'
    Set-Content -LiteralPath $disabledTempPath -Value $disabledDiagnosticConfig -Encoding ASCII
    Move-Item -LiteralPath $disabledTempPath -Destination $diagnosticConfigPath -Force
    $resolvedHostAddresses = @([Net.Dns]::GetHostAddresses('clinica.test'))
    $loopbackV4 = [Net.IPAddress]::Parse('127.0.0.1')
    if ($resolvedHostAddresses.Count -eq 0 -or -not ($resolvedHostAddresses | Where-Object { $_.Equals($loopbackV4) }) -or @($resolvedHostAddresses | Where-Object { -not [Net.IPAddress]::IsLoopback($_) }).Count -gt 0) {
        throw 'CLINICA_TEST_NAME_NOT_LOOPBACK_ONLY'
    }
    if (Get-NetTCPConnection -State Listen -LocalAddress '127.0.0.1' -LocalPort 8766 -ErrorAction SilentlyContinue) { throw 'LOCAL_STT_PORT_ALREADY_IN_USE' }
    $normalOrigin = 'https://clinica.test'
    foreach ($probe in @(
        @{ path = '/assets/css/local-voice-diagnostic.css'; expected = @(200) }
    )) {
        $request = [Net.HttpWebRequest]::Create($normalOrigin + $probe.path)
        $request.Method = 'GET'
        $request.AllowAutoRedirect = $false
        $request.Timeout = 7000
        $request.Proxy = $null
        $request.CookieContainer = New-Object Net.CookieContainer
        $response = $null
        try { $response = [Net.HttpWebResponse]$request.GetResponse() }
        catch [Net.WebException] { $response = $_.Exception.Response }
        if (-not $response) { throw 'NORMAL_HTTPS_PREFLIGHT_TRANSPORT_FAILED' }
        try {
            if ([int]$response.StatusCode -notin $probe.expected) { throw 'NORMAL_HTTPS_PREFLIGHT_STATUS_FAILED' }
        } finally { $response.Close() }
    }

    $pythonBaseOutput = @(& $PythonExe -c "import sys; print(getattr(sys, '_base_executable', sys.executable))")
    if ($LASTEXITCODE -ne 0 -or $pythonBaseOutput.Count -eq 0) { throw 'VENV_BASE_PYTHON_UNAVAILABLE' }
    $pythonBaseExecutable = [IO.Path]::GetFullPath([string]$pythonBaseOutput[-1].Trim())
    if (-not (Test-Path -LiteralPath $pythonBaseExecutable -PathType Leaf)) { throw 'VENV_BASE_PYTHON_UNAVAILABLE' }
    $pythonExecutableAllowlist = @([IO.Path]::GetFullPath($PythonExe), $pythonBaseExecutable) | Select-Object -Unique
    $runRoot = Join-Path (Join-Path $runtimeRoot 'runs') ([Guid]::NewGuid().ToString('N'))
    New-Item -ItemType Directory -Force -Path $runRoot | Out-Null
    $gatewayArgs = @('-u', $gatewayScript, '--host', '127.0.0.1', '--port', '8766', '--origin', $normalOrigin, '--model', $modelPath, '--ticket-key', $ticketKey, '--cert', $certificate, '--private-key', $privateKey)
    $gatewayProcess = $null
    $gatewayListenerPid = 0
    $sensitiveEnvironment = @([Environment]::GetEnvironmentVariables('Process').Keys | Where-Object {
        $_ -match '^(DB_|DATABASE_|MYSQL_|APP_KEY$|GEMINI_|OPENAI_|CLINICAL_AI_|M07_)'
    })
    $previousSensitiveEnvironment = @{}
    try {
        foreach ($name in $sensitiveEnvironment) {
            $previousSensitiveEnvironment[$name] = [Environment]::GetEnvironmentVariable([string]$name, 'Process')
            [Environment]::SetEnvironmentVariable([string]$name, $null, 'Process')
        }
        $gatewayProcess = Start-Process -FilePath $PythonExe -ArgumentList (($gatewayArgs | ForEach-Object { if ($_ -match '\s') { '"' + $_ + '"' } else { $_ } }) -join ' ') -WorkingDirectory $runtimeRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runRoot 'gateway-stdout.log') -RedirectStandardError (Join-Path $runRoot 'gateway-stderr.log') -PassThru
    } finally {
        foreach ($name in $sensitiveEnvironment) { [Environment]::SetEnvironmentVariable([string]$name, $previousSensitiveEnvironment[$name], 'Process') }
    }
    try {
        $deadline = [DateTime]::UtcNow.AddSeconds(20)
        $gatewayListener = $null
        while ([DateTime]::UtcNow -lt $deadline) {
            if ($gatewayProcess.HasExited) { throw 'VOSK_GATEWAY_EXITED_EARLY' }
            $gatewayListener = Get-NetTCPConnection -State Listen -LocalAddress '127.0.0.1' -LocalPort 8766 -ErrorAction SilentlyContinue | Select-Object -First 1
            if ($gatewayListener) { break }
            Start-Sleep -Milliseconds 200
        }
        $gatewayListenerPid = if ($gatewayListener) { [int]$gatewayListener.OwningProcess } else { 0 }
        $gatewayListenerInfo = if ($gatewayListenerPid -gt 0) { Get-CimInstance Win32_Process -Filter ("ProcessId = {0}" -f $gatewayListenerPid) -ErrorAction SilentlyContinue } else { $null }
        $gatewayListenerExecutable = if ($gatewayListenerInfo) { [IO.Path]::GetFullPath([string]$gatewayListenerInfo.ExecutablePath) } else { '' }
        if (-not $gatewayListener -or -not $gatewayListenerInfo -or $gatewayListenerExecutable -notin $pythonExecutableAllowlist -or [string]$gatewayListenerInfo.CommandLine -notlike ('*' + $gatewayScript + '*')) { throw 'VOSK_GATEWAY_LISTENER_IDENTITY_FAILED' }
        $state = [ordered]@{
            mode = 'normal_application_gateway'; apache_owned = $false; apache_pid = $null; apache_port = $null
            gateway_pid = [int]$gatewayProcess.Id; gateway_executable = [IO.Path]::GetFullPath($PythonExe); gateway_base_executable = $pythonBaseExecutable
            gateway_script = $gatewayScript; gateway_listener_pid = $gatewayListenerPid; gateway_listener_executable = $gatewayListenerExecutable; gateway_port = 8766
            database = 'NOT_USED'; origin = $normalOrigin; started_utc = [DateTime]::UtcNow.ToString('o')
        }
        $state | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $runRoot 'process.json') -Encoding UTF8
        if ($EnableDiagnosticPage) {
            $enabledDiagnosticConfig = [ordered]@{ version = 1; enabled = $true; origin = $normalOrigin } | ConvertTo-Json -Compress
            $enabledTempPath = $diagnosticConfigPath + '.tmp'
            Set-Content -LiteralPath $enabledTempPath -Value $enabledDiagnosticConfig -Encoding ASCII
            Move-Item -LiteralPath $enabledTempPath -Destination $diagnosticConfigPath -Force

            $diagnosticRequest = [Net.HttpWebRequest]::Create($normalOrigin + '/historia-clinica/diagnostico-voz')
            $diagnosticRequest.Method = 'GET'
            $diagnosticRequest.AllowAutoRedirect = $false
            $diagnosticRequest.Timeout = 7000
            $diagnosticRequest.Proxy = $null
            $diagnosticRequest.CookieContainer = New-Object Net.CookieContainer
            $diagnosticResponse = $null
            try { $diagnosticResponse = [Net.HttpWebResponse]$diagnosticRequest.GetResponse() }
            catch [Net.WebException] { $diagnosticResponse = $_.Exception.Response }
            if (-not $diagnosticResponse) { throw 'DIAGNOSTIC_HTTP_AUTH_PREFLIGHT_TRANSPORT_FAILED' }
            try {
                if ([int]$diagnosticResponse.StatusCode -ne 302 -or $diagnosticResponse.Headers['Location'] -ne '/historia-clinica/acceso') {
                    throw 'DIAGNOSTIC_HTTP_AUTH_PREFLIGHT_FAILED'
                }
            } finally { $diagnosticResponse.Close() }
        }
        [ordered]@{ status = 'STARTED'; mode = $state.mode; database = 'NOT_USED'; origin = $normalOrigin; apache_owned = $false; gateway_pid = $state.gateway_pid; runtime = $runRoot; diagnostic_page_enabled = [bool]$EnableDiagnosticPage; m07_gate = 'CLOSED_BY_DEFAULT' } | ConvertTo-Json -Compress
        return
    } catch {
        $cleanupIds = @(); if ($gatewayProcess) { $cleanupIds += [int]$gatewayProcess.Id }; if ($gatewayListenerPid -gt 0) { $cleanupIds += $gatewayListenerPid }
        foreach ($processId in @($cleanupIds | Select-Object -Unique)) {
            $info = Get-CimInstance Win32_Process -Filter ("ProcessId = {0}" -f [int]$processId) -ErrorAction SilentlyContinue
            if ($info -and [IO.Path]::GetFullPath([string]$info.ExecutablePath) -in $pythonExecutableAllowlist -and [string]$info.CommandLine -like ('*' + $gatewayScript + '*')) { & taskkill.exe /PID ([int]$processId) /T /F *> $null }
        }
        throw
    }
}
foreach ($port in @(8443, 8766)) {
    if (Get-NetTCPConnection -State Listen -LocalAddress '127.0.0.1' -LocalPort $port -ErrorAction SilentlyContinue) { throw 'LOCAL_STT_PORT_ALREADY_IN_USE' }
}

$envFile = Join-Path $repo '.env'
if (-not (Test-Path -LiteralPath $envFile -PathType Leaf)) { throw 'LOCAL_ENV_FILE_MISSING' }
$localEnv = @{}
foreach ($line in Get-Content -LiteralPath $envFile) {
    $trimmed = $line.Trim()
    if ($trimmed -eq '' -or $trimmed.StartsWith('#') -or $trimmed.IndexOf('=') -lt 1) { continue }
    $parts = $trimmed.Split('=', 2)
    $key = $parts[0].Trim()
    $value = $parts[1].Trim()
    if ($value.Length -ge 2 -and (($value.StartsWith('"') -and $value.EndsWith('"')) -or ($value.StartsWith("'") -and $value.EndsWith("'")))) { $value = $value.Substring(1, $value.Length - 2) }
    $localEnv[$key] = $value
}
foreach ($key in @('DB_USERNAME', 'DB_PASSWORD')) { if (-not $localEnv.ContainsKey($key) -or $localEnv[$key] -eq '') { throw 'SYNTHETIC_DATABASE_CREDENTIALS_MISSING' } }

$runRoot = Join-Path (Join-Path $runtimeRoot 'runs') ([Guid]::NewGuid().ToString('N'))
$sessions = Join-Path $runRoot 'sessions'
$tmp = Join-Path $runRoot 'tmp'
New-Item -ItemType Directory -Force -Path $sessions, $tmp | Out-Null
$apacheConfig = Join-Path $runRoot 'httpd.conf'
$runtimeForApache = $runRoot.Replace('\', '/')
$rootForApache = $runtimeRoot.Replace('\', '/')
$resolved = (Get-Content -LiteralPath $templatePath -Raw).
    Replace('__VOSK_RUNTIME__', $runtimeForApache).
    Replace('__VOSK_CERT__', $certificate.Replace('\', '/')).
    Replace('__VOSK_CERT_KEY__', $privateKey.Replace('\', '/')).
    Replace('__VOSK_ROOT__', $rootForApache)
Set-Content -LiteralPath $apacheConfig -Value $resolved -Encoding UTF8

$apacheConfigStart = New-Object System.Diagnostics.ProcessStartInfo
$apacheConfigStart.FileName = $ApacheExe
$apacheConfigStart.Arguments = '-f "' + $apacheConfig + '" -d "C:\xampp\apache" -t'
$apacheConfigStart.WorkingDirectory = 'C:\xampp\apache'
$apacheConfigStart.UseShellExecute = $false
$apacheConfigStart.CreateNoWindow = $true
$apacheConfigStart.RedirectStandardOutput = $true
$apacheConfigStart.RedirectStandardError = $true
$apacheConfigTest = New-Object System.Diagnostics.Process
$apacheConfigTest.StartInfo = $apacheConfigStart
if (-not $apacheConfigTest.Start()) { throw 'ISOLATED_APACHE_CONFIG_TEST_START_FAILED' }
$apacheConfigStdoutTask = $apacheConfigTest.StandardOutput.ReadToEndAsync()
$apacheConfigStderrTask = $apacheConfigTest.StandardError.ReadToEndAsync()
if (-not $apacheConfigTest.WaitForExit(10000)) {
    $apacheConfigTest.Kill()
    $apacheConfigTest.WaitForExit()
    throw 'ISOLATED_APACHE_CONFIG_TIMEOUT'
}
$apacheConfigTest.WaitForExit()
$apacheConfigExitCode = [int]$apacheConfigTest.ExitCode
$apacheConfigOutputText = $apacheConfigStdoutTask.Result + "`n" + $apacheConfigStderrTask.Result
$apacheSyntaxPassed = $apacheConfigOutputText -match '(?m)^\s*Syntax OK\s*$'
if ($apacheConfigExitCode -ne 0 -or -not $apacheSyntaxPassed) {
    [ordered]@{ stage = 'apache_config_test'; exit_code = $apacheConfigExitCode; syntax_marker = if ($apacheSyntaxPassed) { 'PASS' } else { 'MISSING' } } | ConvertTo-Json -Compress
    throw 'ISOLATED_APACHE_CONFIG_INVALID'
}

$values = [ordered]@{
    APP_ENV = 'testing'; APP_KEY = [Guid]::NewGuid().ToString('N'); APP_NAME = 'ClinicaDolor-M07-Synthetic'
    DB_HOST = '127.0.0.1'; DB_PORT = '3306'; DB_DATABASE = $syntheticDatabase
    DB_USERNAME = [string]$localEnv['DB_USERNAME']; DB_PASSWORD = [string]$localEnv['DB_PASSWORD']
    CLINICAL_AI_SYNTHETIC_MODE = '1'; CLINICAL_AI_EXTERNAL_ENABLED = '0'; CLINICAL_AI_SUMMARY_ENABLED = '0'
    GEMINI_API_KEY = ''; GEMINI_ENDPOINT = ''; OPENAI_API_KEY = ''; M07_HTTP_ISOLATED = '1'; PREFLIGHT_ONLY = '0'
}
$previous = @{}
foreach ($name in $values.Keys) { $previous[$name] = [Environment]::GetEnvironmentVariable($name, 'Process') }
$apacheProcess = $null
$gatewayProcess = $null
$gatewayListenerPid = 0
try {
    foreach ($name in $values.Keys) { [Environment]::SetEnvironmentVariable($name, [string]$values[$name], 'Process') }
    $apacheProcess = Start-Process -FilePath $ApacheExe -ArgumentList @('-f', ('"' + $apacheConfig + '"'), '-d', '"C:\xampp\apache"') -WorkingDirectory 'C:\xampp\apache' -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runRoot 'apache-stdout.log') -RedirectStandardError (Join-Path $runRoot 'apache-stderr.log') -PassThru
} finally {
    foreach ($name in $values.Keys) { [Environment]::SetEnvironmentVariable($name, $previous[$name], 'Process') }
}

try {
$deadline = [DateTime]::UtcNow.AddSeconds(12)
while ([DateTime]::UtcNow -lt $deadline) {
    if ($apacheProcess.HasExited) { throw 'ISOLATED_APACHE_EXITED_EARLY' }
    $listener = Get-NetTCPConnection -State Listen -LocalAddress '127.0.0.1' -LocalPort 8443 -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($listener) { break }
    Start-Sleep -Milliseconds 150
}
if (-not $listener -or [int]$listener.OwningProcess -ne $apacheProcess.Id) { throw 'ISOLATED_APACHE_LISTENER_IDENTITY_FAILED' }

# The permanent prepend guard performs SELECT DATABASE() before the router.
# A deliberately unknown path avoids accessing a clinical page.
$request = [Net.HttpWebRequest]::Create($expectedOrigin + '/__local_vosk_guard_probe__')
$request.Method = 'GET'
$request.AllowAutoRedirect = $false
$request.Timeout = 5000
$guardPassed = $false
try {
    $response = [Net.HttpWebResponse]$request.GetResponse()
} catch [Net.WebException] {
    $response = $_.Exception.Response
}
if ($response) {
    try { $guardPassed = $response.Headers['X-M07-Database-Guard'] -eq 'PASS' } finally { $response.Close() }
}
if (-not $guardPassed) { throw 'EFFECTIVE_SYNTHETIC_DATABASE_GUARD_FAILED' }

$gatewayArgs = @('-u', $gatewayScript, '--host', '127.0.0.1', '--port', '8766', '--origin', $expectedOrigin, '--model', $modelPath, '--ticket-key', $ticketKey, '--cert', $certificate, '--private-key', $privateKey)
$gatewayProcess = Start-Process -FilePath $PythonExe -ArgumentList (($gatewayArgs | ForEach-Object { if ($_ -match '\s') { '"' + $_ + '"' } else { $_ } }) -join ' ') -WorkingDirectory $runtimeRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runRoot 'gateway-stdout.log') -RedirectStandardError (Join-Path $runRoot 'gateway-stderr.log') -PassThru
$deadline = [DateTime]::UtcNow.AddSeconds(20)
$gatewayListener = $null
while ([DateTime]::UtcNow -lt $deadline) {
    if ($gatewayProcess.HasExited) { throw 'VOSK_GATEWAY_EXITED_EARLY' }
    $gatewayListener = Get-NetTCPConnection -State Listen -LocalAddress '127.0.0.1' -LocalPort 8766 -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($gatewayListener) { break }
    Start-Sleep -Milliseconds 200
}
$gatewayListenerPid = if ($gatewayListener) { [int]$gatewayListener.OwningProcess } else { 0 }
$gatewayListenerInfo = if ($gatewayListenerPid -gt 0) { Get-CimInstance Win32_Process -Filter ("ProcessId = {0}" -f $gatewayListenerPid) -ErrorAction SilentlyContinue } else { $null }
$gatewayExpectedExecutable = [IO.Path]::GetFullPath($PythonExe)
$gatewayListenerExecutable = if ($gatewayListenerInfo) { [IO.Path]::GetFullPath([string]$gatewayListenerInfo.ExecutablePath) } else { '' }
$gatewayProcessIdentityValid = $gatewayListenerInfo -and
    $gatewayListenerExecutable -in $pythonExecutableAllowlist -and
    [string]$gatewayListenerInfo.CommandLine -like ('*' + $gatewayScript + '*')
if (-not $gatewayListener -or -not $gatewayProcessIdentityValid) {
    [ordered]@{
        stage = 'gateway_listener_identity'
        listener_found = [bool]$gatewayListener
        launcher_pid = [int]$gatewayProcess.Id
        listener_pid = $gatewayListenerPid
        owner_process_found = [bool]$gatewayListenerInfo
        executable_matches_allowed_python = [bool]($gatewayListenerInfo -and $gatewayListenerExecutable -in $pythonExecutableAllowlist)
        script_matches = [bool]($gatewayListenerInfo -and [string]$gatewayListenerInfo.CommandLine -like ('*' + $gatewayScript + '*'))
    } | ConvertTo-Json -Compress
    throw 'VOSK_GATEWAY_LISTENER_IDENTITY_FAILED'
}

$state = [ordered]@{
    runtime = $runRoot; apache_pid = [int]$apacheProcess.Id; apache_executable = [IO.Path]::GetFullPath($ApacheExe); apache_config = $apacheConfig; apache_listener_pid = [int]$listener.OwningProcess; apache_port = 8443
    gateway_pid = [int]$gatewayProcess.Id; gateway_executable = $gatewayExpectedExecutable; gateway_base_executable = $pythonBaseExecutable; gateway_script = $gatewayScript; gateway_listener_pid = $gatewayListenerPid; gateway_listener_executable = $gatewayListenerExecutable; gateway_port = 8766
    database = $syntheticDatabase; origin = $expectedOrigin; started_utc = [DateTime]::UtcNow.ToString('o')
}
$state | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $runRoot 'process.json') -Encoding UTF8
[ordered]@{ status = 'STARTED'; database = $syntheticDatabase; origin = $expectedOrigin; apache_pid = $state.apache_pid; gateway_pid = $state.gateway_pid; runtime = $runRoot; m07_gate = 'CLOSED_BY_DEFAULT' } | ConvertTo-Json -Compress
} catch {
    $gatewayCleanupIds = @()
    if ($gatewayProcess) { $gatewayCleanupIds += [int]$gatewayProcess.Id }
    if ($gatewayListenerPid -gt 0) { $gatewayCleanupIds += [int]$gatewayListenerPid }
    foreach ($gatewayCleanupId in @($gatewayCleanupIds | Select-Object -Unique)) {
        $gatewayInfo = Get-CimInstance Win32_Process -Filter ("ProcessId = {0}" -f [int]$gatewayCleanupId) -ErrorAction SilentlyContinue
        if ($gatewayInfo -and [IO.Path]::GetFullPath([string]$gatewayInfo.ExecutablePath) -in $pythonExecutableAllowlist -and [string]$gatewayInfo.CommandLine -like ('*' + $gatewayScript + '*')) {
            & taskkill.exe /PID ([int]$gatewayCleanupId) /T /F *> $null
        }
    }
    if ($apacheProcess) {
        $apacheInfo = Get-CimInstance Win32_Process -Filter ("ProcessId = {0}" -f [int]$apacheProcess.Id) -ErrorAction SilentlyContinue
        if ($apacheInfo -and [IO.Path]::GetFullPath([string]$apacheInfo.ExecutablePath) -eq [IO.Path]::GetFullPath($ApacheExe) -and [string]$apacheInfo.CommandLine -like ('*' + $apacheConfig + '*')) {
            & taskkill.exe /PID $apacheProcess.Id /T /F *> $null
        }
    }
    $apacheInfo = Get-CimInstance Win32_Process -ErrorAction SilentlyContinue | Where-Object { $_.Name -ieq 'httpd.exe' -and [string]$_.CommandLine -like ('*' + [string]$apacheConfig + '*') }
    foreach ($candidate in @($apacheInfo)) {
        if ([IO.Path]::GetFullPath([string]$candidate.ExecutablePath) -eq [IO.Path]::GetFullPath($ApacheExe)) {
            & taskkill.exe /PID ([int]$candidate.ProcessId) /T /F *> $null
        }
    }
    $cleanupDeadline = [DateTime]::UtcNow.AddSeconds(5)
    do {
        $remainingGateway = @($gatewayCleanupIds | Select-Object -Unique | ForEach-Object { Get-CimInstance Win32_Process -Filter ("ProcessId = {0}" -f [int]$_) -ErrorAction SilentlyContinue } | Where-Object { $_ })
        $remainingApache = Get-CimInstance Win32_Process -ErrorAction SilentlyContinue | Where-Object { $_.Name -ieq 'httpd.exe' -and [string]$_.CommandLine -like ('*' + [string]$apacheConfig + '*') }
        $remainingListeners = @(Get-NetTCPConnection -State Listen -LocalAddress '127.0.0.1' -ErrorAction SilentlyContinue | Where-Object { $_.LocalPort -in @(8443, 8766) })
        if ($remainingGateway.Count -eq 0 -and -not $remainingApache -and $remainingListeners.Count -eq 0) { break }
        Start-Sleep -Milliseconds 150
    } while ([DateTime]::UtcNow -lt $cleanupDeadline)
    if ($remainingGateway.Count -gt 0 -or $remainingApache -or $remainingListeners.Count -gt 0) { Write-Warning 'START_FAILURE_CLEANUP=FAIL' } else { Write-Warning 'START_FAILURE_CLEANUP=PASS' }
    throw
}
