param(
    [Alias('Pid')]
    [Parameter(Mandatory = $true)]
    [int]$ProcessId
)

$ErrorActionPreference = 'Stop'
$process = Get-Process -Id $ProcessId -ErrorAction SilentlyContinue
if ($null -eq $process) {
    Write-Output "PID=$ProcessId no está activo."
    exit 0
}

Stop-Process -Id $ProcessId
Write-Output "Servidor P04 detenido: PID=$ProcessId"

