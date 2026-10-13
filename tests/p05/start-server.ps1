param(
    [int]$Port = 8087,
    [string]$Database = 'clinica_dolor_demo_20261010_final',
    [string]$AppKey = ''
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
if ($Database -ne 'clinica_dolor_demo_20261010_final') {
    throw 'Fail-closed: sólo se permite la base sintética autorizada de P05.'
}
if ([string]::IsNullOrWhiteSpace($AppKey)) {
    $AppKey = [Guid]::NewGuid().ToString('N')
}

$prepare = & php (Join-Path $PSScriptRoot 'prepare-synthetic.php') "--database=$Database" '--repair-schema' '--seed-catalog' '--json'
if ($LASTEXITCODE -ne 0) { throw 'La preparación sintética de P05 rechazó el entorno.' }
$prepared = ($prepare -join "`n") | ConvertFrom-Json
if (-not $prepared.ok -or $prepared.database -ne $Database -or $prepared.theme_count -ne 7 -or $prepared.question_count -ne 25) {
    throw 'El catálogo sintético P05 no confirmó 7 temas y 25 preguntas.'
}

$routerPath = Join-Path $root 'tests\p04\router.php'
$stdoutPath = Join-Path ([IO.Path]::GetTempPath()) "clinica-p05-$Port.stdout.log"
$stderrPath = Join-Path ([IO.Path]::GetTempPath()) "clinica-p05-$Port.stderr.log"
$startInfo = [System.Diagnostics.ProcessStartInfo]::new()
$startInfo.FileName = 'php.exe'
$startInfo.Arguments = "-d variables_order=EGPCS -S 127.0.0.1:$Port `"$routerPath`""
$startInfo.WorkingDirectory = $root
$startInfo.UseShellExecute = $false
$startInfo.CreateNoWindow = $true
$startInfo.RedirectStandardOutput = $false
$startInfo.RedirectStandardError = $false
foreach ($entry in @{
        DB_DATABASE = $Database
        P04_TEST_DATABASE = $Database
        P05_TEST_DATABASE = $Database
        P04_TEST_MODE = '1'
        APP_ENV = 'testing'
        APP_KEY = $AppKey
    }.GetEnumerator()) {
    $startInfo.Environment[$entry.Key] = $entry.Value
}
$server = [System.Diagnostics.Process]::new()
$server.StartInfo = $startInfo
$server.Start() | Out-Null

Start-Sleep -Milliseconds 350
if ($server.HasExited) {
    throw "El servidor terminó inmediatamente. Revisa $stdoutPath y $stderrPath"
}

$ready = $false
for ($attempt = 0; $attempt -lt 20; $attempt++) {
    $listener = Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($listener) {
        if ([int]$listener.OwningProcess -ne $server.Id) {
            throw "El puerto $Port está atendido por otro proceso ($($listener.OwningProcess)); se detiene el arranque para evitar probar contra una instancia ajena."
        }
        $ready = $true
        break
    }
    Start-Sleep -Milliseconds 100
}
if (-not $ready) {
    throw "El proceso $($server.Id) no abrió el listener esperado en 127.0.0.1:$Port."
}

Write-Output "PID=$($server.Id)"
Write-Output "Base=$Database"
Write-Output "URL=http://127.0.0.1:$Port"
Write-Output "Stdout=$stdoutPath"
Write-Output "Stderr=$stderrPath"
