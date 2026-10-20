[CmdletBinding()]
param([string]$StatePath = (Join-Path $PSScriptRoot 'runtime-state.json'))

$ErrorActionPreference = 'Stop'
if (-not (Test-Path $StatePath)) {
    $orphanTargets = @()
    $orphanListeners = @(Get-NetTCPConnection -LocalAddress 127.0.0.1 -LocalPort 8766,8767 -State Listen -ErrorAction SilentlyContinue | Select-Object LocalPort,OwningProcess -Unique)
    foreach ($listener in $orphanListeners) {
        $candidate = Get-CimInstance Win32_Process -Filter "ProcessId = $($listener.OwningProcess)" -ErrorAction Stop
        $command = ([string]$candidate.CommandLine).ToLowerInvariant()
        $isPython = $listener.LocalPort -eq 8766 -and $command.Contains('voice-stt-02\stt_service.py') -and $command.Contains('--port 8766')
        $isPhp = $listener.LocalPort -eq 8767 -and $command.Contains('voice-stt-02\bridge.php') -and $command.Contains('127.0.0.1:8767')
        if (-not ($isPython -or $isPhp)) {
            throw "El listener $($listener.OwningProcess) no coincide inequívocamente con VOICE-STT-02; no se detuvo ningún proceso adicional."
        }
        $orphanTargets += @{Id=[int]$listener.OwningProcess;Path=[string]$candidate.ExecutablePath}
    }
    if (-not $orphanTargets.Count) { Write-Output 'VOICE_STT_02_NOT_RUNNING'; exit 0 }
    foreach ($entry in ($orphanTargets | Group-Object Id | ForEach-Object { $_.Group[0] })) {
        $process = Get-Process -Id $entry.Id -ErrorAction SilentlyContinue
        if (-not $process) { continue }
        $actualPath = try { $process.Path } catch { '' }
        if ($actualPath -and ([IO.Path]::GetFullPath($actualPath) -ne [IO.Path]::GetFullPath($entry.Path))) { throw "PID $($entry.Id) no coincide con el ejecutable esperado; no se detuvo." }
        Stop-Process -Id $entry.Id -Force -ErrorAction SilentlyContinue
    }
    Start-Sleep -Milliseconds 500
    $remainingOrphans = @(Get-NetTCPConnection -LocalAddress 127.0.0.1 -LocalPort 8766,8767 -State Listen -ErrorAction SilentlyContinue)
    if ($remainingOrphans.Count) { throw 'Persisten listeners después del cierre focal; se conservó el bloqueo por seguridad.' }
    Write-Output 'VOICE_STT_02_STOPPED_ORPHAN_RECOVERY'
    exit 0
}
$state = Get-Content -LiteralPath $StatePath -Raw | ConvertFrom-Json
$targets = @(
    @{Id=[int]$state.PhpPid;Path=[string]$state.PhpPath},
    @{Id=[int]$state.PythonPid;Path=[string]$state.PythonPath}
)
$listeners = @(Get-NetTCPConnection -LocalAddress 127.0.0.1 -LocalPort ([int]$state.PythonPort) -State Listen -ErrorAction SilentlyContinue | Select-Object -ExpandProperty OwningProcess -Unique)
foreach ($listenerPid in $listeners) {
    $candidate = Get-CimInstance Win32_Process -Filter "ProcessId = $listenerPid" -ErrorAction Stop
    $command = [string]$candidate.CommandLine
    if (-not $command.Contains('voice-stt-02\stt_service.py') -or -not $command.Contains("--port $($state.PythonPort)")) {
        throw "El listener $listenerPid no coincide inequívocamente con VOICE-STT-02; no se detuvo ningún proceso adicional."
    }
    $targets += @{Id=[int]$listenerPid;Path=[string]$candidate.ExecutablePath}
}
$targets = $targets | Group-Object Id | ForEach-Object { $_.Group[0] }
foreach ($entry in $targets) {
    $process = Get-Process -Id $entry.Id -ErrorAction SilentlyContinue
    if (-not $process) { continue }
    $actualPath = try { $process.Path } catch { '' }
    if ($actualPath -and ([IO.Path]::GetFullPath($actualPath) -ne [IO.Path]::GetFullPath($entry.Path))) { throw "PID $($entry.Id) no coincide con el ejecutable esperado; no se detuvo." }
    Stop-Process -Id $entry.Id -Force -ErrorAction SilentlyContinue
    Wait-Process -Id $entry.Id -Timeout 5 -ErrorAction SilentlyContinue
}
$remaining = @(Get-NetTCPConnection -LocalAddress 127.0.0.1 -LocalPort ([int]$state.PythonPort),([int]$state.PhpPort) -State Listen -ErrorAction SilentlyContinue)
if ($remaining.Count) { throw 'Persisten listeners después del cierre; se conservó runtime-state.json para permitir otra limpieza segura.' }
Remove-Item -LiteralPath $StatePath -Force
Write-Output 'VOICE_STT_02_STOPPED'
