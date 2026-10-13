param([int]$Port = 8098)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$database = 'clinica_dolor_browser_20261012'
$listener = Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue
if ($listener) { throw "Fail-closed: el puerto $Port ya está ocupado." }
$routerPath = Join-Path $root 'tests\p04\router.php'
$startInfo = [System.Diagnostics.ProcessStartInfo]::new()
$startInfo.FileName = 'php.exe'
$startInfo.Arguments = "-d variables_order=EGPCS -S 127.0.0.1:$Port `"$routerPath`""
$startInfo.WorkingDirectory = $root
$startInfo.UseShellExecute = $false
$startInfo.CreateNoWindow = $true
$startInfo.RedirectStandardOutput = $false
$startInfo.RedirectStandardError = $false
foreach ($entry in @{DB_DATABASE=$database;P04_TEST_DATABASE=$database;APP_ENV='testing';APP_KEY=([Guid]::NewGuid().ToString('N'))}.GetEnumerator()) { $startInfo.Environment[$entry.Key] = $entry.Value }
$server = [System.Diagnostics.Process]::new(); $server.StartInfo = $startInfo; $server.Start() | Out-Null
Start-Sleep -Milliseconds 350
for ($attempt=0; $attempt -lt 20; $attempt++) {
    $listener = Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($listener) {
        if ([int]$listener.OwningProcess -ne $server.Id) { throw "Fail-closed: PID de listener inesperado ($($listener.OwningProcess))." }
        Write-Output "PID=$($server.Id)"; Write-Output "PORT=$Port"; Write-Output "DATABASE=$database"; Write-Output "URL=http://127.0.0.1:$Port"; exit 0
    }
    Start-Sleep -Milliseconds 100
}
throw "El PID $($server.Id) no abrió el puerto $Port."
