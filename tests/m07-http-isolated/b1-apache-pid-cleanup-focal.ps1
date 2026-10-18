[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path

function Assert-Condition([bool]$Condition, [string]$Message) {
    if (-not $Condition) { throw $Message }
}

function Get-ReservedPidAssignments([string]$Path) {
    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) { throw ('Falta el archivo: ' + $Path) }
    return @(Select-String -LiteralPath $Path -Pattern '^\s*\$pid\s*=' -CaseSensitive:$false -AllMatches)
}

function Invoke-SimulatedLaunch([bool]$Completed, [string]$Status, [int]$ExitCode) {
    return [PSCustomObject]@{
        Completed = $Completed
        Status = $Status
        ExitCode = $ExitCode
    }
}

function Invoke-SimulatedCleanup([string]$OriginalFailure, [bool]$MainStopped, [bool]$WorkerResidual, [bool]$UnknownIdentity, [int]$StopExitCode) {
    $cleanupFailure = $false
    $cleanupMessage = ''
    if (-not $MainStopped) {
        $cleanupFailure = $true
        $cleanupMessage = 'El proceso principal no confirmó su detención.'
    } elseif ($WorkerResidual) {
        $cleanupFailure = $true
        $cleanupMessage = 'Quedó un trabajador sintético residual.'
    } elseif ($UnknownIdentity) {
        $cleanupFailure = $true
        $cleanupMessage = 'Se detectó un proceso cuya identidad no pudo verificarse.'
    } elseif ($StopExitCode -ne 0) {
        $cleanupFailure = $true
        $cleanupMessage = 'La detención controlada devolvió un código distinto de cero.'
    }
    return [PSCustomObject]@{
        Status = if ($cleanupFailure) { 'CLEANUP_FAILED' } elseif ([string]::IsNullOrEmpty($OriginalFailure)) { 'PASS' } else { 'BLOCKED_OR_FAILED' }
        OriginalFailure = $OriginalFailure
        CleanupFailure = $cleanupFailure
        CleanupMessage = $cleanupMessage
    }
}

function Resolve-SimulatedAlreadyExited([bool]$ProcessGone, [bool]$ListenerFree, [bool]$WorkersGone, [bool]$UnknownIdentity) {
    if ($ProcessGone -and $ListenerFree -and $WorkersGone -and -not $UnknownIdentity) {
        return 'ALREADY_EXITED_VERIFIED'
    }
    return 'CLEANUP_FAILED'
}

$b1Runner = Join-Path $PSScriptRoot 'run-m07-b1-http-readonly-medium.ps1'
$startScript = Join-Path $PSScriptRoot 'start-m07-apache.ps1'
$stopScript = Join-Path $PSScriptRoot 'stop-m07-apache.ps1'
$a5Runner = Join-Path $PSScriptRoot 'run-m07-http-matrix-medium.ps1'

$reservedMatches = @()
foreach ($path in @($b1Runner, $startScript, $stopScript, $a5Runner)) {
    $reservedMatches += Get-ReservedPidAssignments $path
}
Assert-Condition ($reservedMatches.Count -eq 0) 'Existe una asignación a la variable automática $PID.'

$launchPass = Invoke-SimulatedLaunch $true 'START_COMPLETED' 0
Assert-Condition ($launchPass.Completed -and $launchPass.Status -eq 'START_COMPLETED' -and $launchPass.ExitCode -eq 0) 'Falló la simulación de arranque correcto.'

$launchFail = Invoke-SimulatedLaunch $false 'START_FAILED' 1
Assert-Condition ((-not $launchFail.Completed) -and $launchFail.Status -eq 'START_FAILED' -and $launchFail.ExitCode -eq 1) 'Falló la propagación del resultado de arranque.'

$original = 'La matriz no fue invocada por una precondición fallida.'
$beforeMatrix = Invoke-SimulatedCleanup $original $true $false $false 0
Assert-Condition ($beforeMatrix.Status -eq 'BLOCKED_OR_FAILED' -and $beforeMatrix.OriginalFailure -eq $original -and -not $beforeMatrix.CleanupFailure) 'No se preservó el fallo anterior a la matriz.'

$mainStopped = Invoke-SimulatedCleanup $original $true $false $false 0
Assert-Condition ($mainStopped.OriginalFailure -eq $original -and -not $mainStopped.CleanupFailure) 'La detención normal no fue reconocida.'

$workerResidual = Invoke-SimulatedCleanup $original $true $true $false 0
Assert-Condition ($workerResidual.Status -eq 'CLEANUP_FAILED' -and $workerResidual.CleanupFailure -and $workerResidual.OriginalFailure -eq $original) 'No se detectó el trabajador residual.'

$unknownIdentity = Invoke-SimulatedCleanup $original $true $false $true 0
Assert-Condition ($unknownIdentity.Status -eq 'CLEANUP_FAILED' -and $unknownIdentity.CleanupFailure -and $unknownIdentity.OriginalFailure -eq $original) 'No se bloqueó la identidad de proceso desconocida.'

$stopFailure = Invoke-SimulatedCleanup $original $true $false $false 7
Assert-Condition ($stopFailure.Status -eq 'CLEANUP_FAILED' -and $stopFailure.CleanupFailure -and $stopFailure.OriginalFailure -eq $original) 'No se propagó el código de salida de limpieza.'

$clean = Invoke-SimulatedCleanup '' $true $false $false 0
Assert-Condition ($clean.Status -eq 'PASS' -and -not $clean.CleanupFailure) 'La limpieza simulada correcta no pasó.'

$alreadyExited = Resolve-SimulatedAlreadyExited $true $true $true $false
Assert-Condition ($alreadyExited -eq 'ALREADY_EXITED_VERIFIED') 'No se reconoció un runtime ya terminado y limpio.'

$listenerOccupied = Resolve-SimulatedAlreadyExited $true $false $true $false
Assert-Condition ($listenerOccupied -eq 'CLEANUP_FAILED') 'Se aceptó un listener ocupado como limpieza correcta.'

Write-Output ('PS_VERSION=' + $PSVersionTable.PSVersion.ToString())
Write-Output 'APACHE_STARTED=NO'
Write-Output 'MYSQL_CONNECTED=NO'
Write-Output 'HTTP_EXECUTED=NO'
Write-Output 'RESERVED_PID_ASSIGNMENTS=0'
Write-Output 'SIMULATED_START=PASS'
Write-Output 'SIMULATED_FAILURE_BEFORE_MATRIX=PASS'
Write-Output 'SIMULATED_MAIN_PROCESS_CLEANUP=PASS'
Write-Output 'SIMULATED_ALREADY_EXITED_VERIFIED=PASS'
Write-Output 'SIMULATED_LISTENER_OCCUPIED=CLEANUP_FAILED_EXPECTED'
Write-Output 'SIMULATED_WORKER_RESIDUAL=CLEANUP_FAILED_EXPECTED'
Write-Output 'SIMULATED_UNKNOWN_IDENTITY=CLEANUP_FAILED_EXPECTED'
Write-Output 'SIMULATED_EXIT_CODE_PROPAGATION=CLEANUP_FAILED_EXPECTED'
Write-Output 'ORIGINAL_FAILURE_PRESERVED=PASS'
Write-Output 'RESULT=PASS'
