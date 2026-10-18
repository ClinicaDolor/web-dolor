param(
    [Parameter(Mandatory = $true)]
    [string]$Runtime
)

$ErrorActionPreference = 'Stop'
$statePath = Join-Path $Runtime 'process.json'
if (-not (Test-Path -LiteralPath $statePath -PathType Leaf)) {
    throw 'No existe un estado de proceso sintético verificable.'
}

$state = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
$recordedPid = [int]$state.pid
$port = [int]$state.port
$expectedExecutable = [IO.Path]::GetFullPath([string]$state.executable)
$configPath = [IO.Path]::GetFullPath([string]$state.config)
$alreadyExitedVerified = $false

function Get-ProcessRecordById([int]$ProcessId) {
    if ($ProcessId -le 0) { return $null }
    try {
        return Get-CimInstance Win32_Process -Filter ('ProcessId = {0}' -f $ProcessId) -ErrorAction Stop | Select-Object -First 1
    } catch {
        $running = Get-Process -Id $ProcessId -ErrorAction SilentlyContinue
        if ($null -eq $running) { return $null }
        throw 'No se pudo inspeccionar la identidad del proceso Apache sintético.'
    }
}

function Get-SyntheticProcessRecords {
    try {
        $records = Get-CimInstance Win32_Process -Filter "Name = 'httpd.exe'" -ErrorAction Stop
    } catch {
        throw 'No se pudo inspeccionar el árbol Apache sintético.'
    }

    $matches = @()
    foreach ($record in $records) {
        $path = ''
        try { $path = [IO.Path]::GetFullPath([string]$record.ExecutablePath) } catch { continue }
        $commandLine = [string]$record.CommandLine
        if ($path -eq $expectedExecutable -and $commandLine.IndexOf($configPath, [StringComparison]::OrdinalIgnoreCase) -ge 0) {
            $matches += $record
        }
    }
    return $matches
}

function Assert-SyntheticProcess([object]$Record) {
    if ($null -eq $Record) {
        throw 'No se pudo verificar la identidad del proceso Apache sintético.'
    }
    $path = [IO.Path]::GetFullPath([string]$Record.ExecutablePath)
    $commandLine = [string]$Record.CommandLine
    if ($path -ne $expectedExecutable -or $commandLine.IndexOf($configPath, [StringComparison]::OrdinalIgnoreCase) -lt 0) {
        throw 'El proceso candidato no corresponde al runtime Apache sintético; no se detiene.'
    }
}

function Get-SyntheticCleanupState {
    $remainingListener = Get-NetTCPConnection -State Listen -LocalAddress '127.0.0.1' -LocalPort $port -ErrorAction SilentlyContinue | Select-Object -First 1
    $remainingSynthetic = @(Get-SyntheticProcessRecords)
    return [PSCustomObject]@{
        Listener = $remainingListener
        SyntheticProcesses = $remainingSynthetic
    }
}

function Assert-TaskkillResult([int]$ProcessId, [int]$ExitCode) {
    if ($ExitCode -eq 0) { return }

    # Apache puede terminar entre la identificación y taskkill. Sólo se
    # acepta ese código no cero si se verifica que este runtime ya quedó limpio.
    $processAfterAttempt = Get-ProcessRecordById $ProcessId
    $cleanupState = Get-SyntheticCleanupState
    if ($null -eq $processAfterAttempt -and
        $null -eq $cleanupState.Listener -and
        $cleanupState.SyntheticProcesses.Count -eq 0) {
        $script:alreadyExitedVerified = $true
        return
    }

    throw ('No se pudo detener el proceso Apache sintético identificado; taskkill_exit_code=' + $ExitCode)
}

$listener = Get-NetTCPConnection -State Listen -LocalAddress '127.0.0.1' -LocalPort $port -ErrorAction SilentlyContinue | Select-Object -First 1
$rootRecord = Get-ProcessRecordById $recordedPid
$targets = @{}

if ($null -ne $rootRecord) {
    Assert-SyntheticProcess $rootRecord
    $targets[$recordedPid] = $rootRecord
} elseif ($listener) {
    $listenerPid = [int]$listener.OwningProcess
    $listenerRecord = Get-ProcessRecordById $listenerPid
    Assert-SyntheticProcess $listenerRecord
    $targets[$listenerPid] = $listenerRecord
}

$identified = @(Get-SyntheticProcessRecords)
foreach ($record in $identified) {
    $syntheticProcessId = [int]$record.ProcessId
    if (-not $targets.ContainsKey($syntheticProcessId)) {
        $targets[$syntheticProcessId] = $record
    }
}

if ($null -ne $listener -and -not $targets.ContainsKey([int]$listener.OwningProcess)) {
    throw 'El listener reservado pertenece a un proceso no identificable como este runtime; no se detiene ningún proceso.'
}

if ($targets.Count -eq 0) {
    Write-Output 'PROCESS_STATUS=ALREADY_EXITED_CLEAN'
    Write-Output ('PORT=' + $port)
    Write-Output ('RUNTIME=' + $Runtime)
    exit 0
}

$rootTarget = $targets[[string]$recordedPid]
if ($null -ne $rootTarget) {
    & taskkill.exe /PID $recordedPid /T /F >$null 2>$null
    $killExitCode = $LASTEXITCODE
    Assert-TaskkillResult $recordedPid $killExitCode
} else {
    foreach ($pidKey in @($targets.Keys)) {
        $syntheticProcessId = [int]$pidKey
        & taskkill.exe /PID $syntheticProcessId /T /F >$null 2>$null
        $killExitCode = $LASTEXITCODE
        Assert-TaskkillResult $syntheticProcessId $killExitCode
    }
}

$clean = $false
for ($attempt = 0; $attempt -lt 50; $attempt++) {
    $cleanupState = Get-SyntheticCleanupState
    if ($null -eq $cleanupState.Listener -and $cleanupState.SyntheticProcesses.Count -eq 0) {
        $clean = $true
        break
    }
    Start-Sleep -Milliseconds 100
}

if (-not $clean) {
    throw 'Apache sintético no confirmó la limpieza del listener y de todos sus procesos identificables.'
}

if ($alreadyExitedVerified) {
    Write-Output 'PROCESS_STATUS=ALREADY_EXITED_VERIFIED'
} else {
    Write-Output 'PROCESS_STATUS=STOPPED_CLEAN'
}
Write-Output ('PROCESS_STOPPED_PID=' + $recordedPid)
Write-Output ('PORT=' + $port)
Write-Output ('RUNTIME=' + $Runtime)
