[CmdletBinding()]
param(
    [int]$PythonPort = 8766,
    [int]$PhpPort = 8767,
    [string]$ModelPath = (Join-Path $PSScriptRoot '..\voice-stt-01\model-cache\models--Systran--faster-whisper-small\snapshots\536b0662742c02347bc0e980a01041f333bce120')
)

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$python = Join-Path $root '..\voice-stt-01\.venv\Scripts\python.exe'
$php = 'C:\xampp\php\php.exe'
$statePath = Join-Path $root 'runtime-state.json'

if (-not (Test-Path $python)) { throw 'No existe el entorno virtual VOICE-STT-01.' }
if (-not (Test-Path $php)) { throw 'No existe C:\xampp\php\php.exe.' }
if (-not (Test-Path $ModelPath)) { throw 'No existe el snapshot local del modelo aprobado.' }
foreach ($port in @($PythonPort, $PhpPort)) {
    if (Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue) { throw "El puerto $port ya está ocupado; no se detuvo ningún proceso." }
}

$token = [Guid]::NewGuid().ToString('N')
$env:VOICE_STT_PROTO_TOKEN = $token
$env:VOICE_STT_PYTHON_PORT = [string]$PythonPort
$pythonProcess = $null
$phpProcess = $null
try {
    $pythonProcess = Start-Process -FilePath $python -ArgumentList @((Join-Path $root 'stt_service.py'), '--bind', '127.0.0.1', '--port', $PythonPort, '--model', (Resolve-Path $ModelPath).Path) -WorkingDirectory $root -PassThru -WindowStyle Hidden
    $phpProcess = Start-Process -FilePath $php -ArgumentList @('-d', 'display_errors=0', '-d', 'log_errors=0', '-S', "127.0.0.1:$PhpPort", (Join-Path $root 'bridge.php')) -WorkingDirectory $root -PassThru -WindowStyle Hidden
    [pscustomobject]@{PythonPid=$pythonProcess.Id;PhpPid=$phpProcess.Id;PythonPath=$python;PhpPath=$php;PythonPort=$PythonPort;PhpPort=$PhpPort;ModelPath=(Resolve-Path $ModelPath).Path} | ConvertTo-Json | Set-Content -LiteralPath $statePath -Encoding utf8
    $healthy = $false
    for ($i=0; $i -lt 30; $i++) {
        Start-Sleep -Milliseconds 250
        try {
            $response = Invoke-WebRequest -Uri "http://127.0.0.1:$PhpPort/health" -UseBasicParsing -TimeoutSec 2
            if ($response.StatusCode -eq 200) { $healthy = $true; break }
        } catch {}
    }
    if (-not $healthy) { throw 'El puente local no respondió en el tiempo previsto.' }
    Write-Output "PYTHON_PID=$($pythonProcess.Id)"
    Write-Output "PHP_BRIDGE_PID=$($phpProcess.Id)"
    Write-Output "URL=http://127.0.0.1:$PhpPort/"
    Write-Output "PYTHON_LISTENER=127.0.0.1:$PythonPort (no accesible desde el navegador)"
} catch {
    foreach ($process in @($phpProcess, $pythonProcess)) { if ($process -and -not $process.HasExited) { Stop-Process -Id $process.Id -Force -ErrorAction SilentlyContinue } }
    Remove-Item -LiteralPath $statePath -Force -ErrorAction SilentlyContinue
    throw
}
