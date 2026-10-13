param(
    [int]$Port = 8109,
    [string]$Database = 'clinica_dolor_demo_20261010_final',
    [string]$AppKey = ''
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
if ($Database -ne 'clinica_dolor_demo_20261010_final') { throw 'Fail-closed: sólo se permite la base sintética autorizada de P09.' }
if ([string]::IsNullOrWhiteSpace($AppKey)) { $AppKey = [Guid]::NewGuid().ToString('N') }

$prepare = & php (Join-Path $PSScriptRoot 'prepare-synthetic.php') "--database=$Database" '--json'
if ($LASTEXITCODE -ne 0) { throw 'El entorno sintético de P09 no está preparado.' }
$prepared = ($prepare -join "`n") | ConvertFrom-Json
if (-not $prepared.ok -or $prepared.database -ne $Database -or $prepared.themes -ne 6 -or $prepared.questions -ne 46) {
    throw 'La base sintética de P09 no confirmó 6 clases y 46 preguntas.'
}

$stdoutPath = Join-Path ([IO.Path]::GetTempPath()) "clinica-p09-$Port.stdout.log"
$stderrPath = Join-Path ([IO.Path]::GetTempPath()) "clinica-p09-$Port.stderr.log"
$routerPath = (Join-Path $root 'tests\p09\router.php').Replace("'", "''")
$bootstrapPath = (Join-Path $root 'tests\p09\server-bootstrap.php').Replace("'", "''")
$rootForChild = $root.Replace("'", "''")
$childCommand = @"
Set-Location '$rootForChild'
`$env:DB_DATABASE='$Database'
`$env:P09_TEST_DATABASE='$Database'
`$env:P04_TEST_DATABASE='$Database'
`$env:P04_TEST_MODE='1'
`$env:APP_ENV='testing'
`$env:APP_KEY='$AppKey'
php -d variables_order=EGPCS -d auto_prepend_file='$bootstrapPath' -S 127.0.0.1:$Port '$routerPath'
"@
$server = Start-Process -FilePath 'pwsh.exe' -ArgumentList @('-NoProfile', '-Command', $childCommand) -WorkingDirectory $root -RedirectStandardOutput $stdoutPath -RedirectStandardError $stderrPath -WindowStyle Hidden -PassThru
Start-Sleep -Milliseconds 350
if ($server.HasExited) { throw "El servidor terminó inmediatamente. Revisa $stdoutPath y $stderrPath" }
Write-Output "PID=$($server.Id)"
Write-Output "Base=$Database"
Write-Output "URL=http://127.0.0.1:$Port"
Write-Output "AppKey=$AppKey"
Write-Output "Stdout=$stdoutPath"
Write-Output "Stderr=$stderrPath"
