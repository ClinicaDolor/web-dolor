[CmdletBinding()]
param(
    [string]$PhpPath = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$database = 'clinica_dolor_demo_20261010_final'
$patientA = 9301
$patientB = 9302
$validator = Join-Path $root 'tests\patient-interview\m07-shared-canonical-validate-existing.php'
$baseline = Join-Path $root 'tests\patient-interview\m07-durable-clinical-baseline.php'

if (-not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) { throw 'No se encontró el ejecutable PHP indicado.' }
if (-not (Test-Path -LiteralPath $validator -PathType Leaf)) { throw 'Falta el validador read-only existente.' }
if (-not (Test-Path -LiteralPath $baseline -PathType Leaf)) { throw 'Falta el arnés de línea base durable.' }

$integrity = (& whoami.exe /groups /fo csv /nh 2>$null | Out-String)
if ($integrity -notmatch '(?i)Medium Mandatory Level') {
    throw 'El ejecutor debe iniciarse desde PowerShell normal con integridad Medium.'
}

$validationRaw = @(& $PhpPath $validator "--database=$database" "--patient-a=$patientA" "--patient-b=$patientB" 2>$null)
$validationExit = $LASTEXITCODE
try {
    $validation = (($validationRaw -join "`n") | ConvertFrom-Json)
} catch {
    throw 'El validador read-only no devolvió JSON controlado.'
}
if ($validationExit -ne 0 -or [string]$validation.status -ne 'PASS_READ_ONLY_EXISTING_SCHEMA') {
    throw 'El preflight clínico read-only no pasó; no se generó una línea base.'
}

& $PhpPath $baseline '--mode=create' "--database=$database" "--patient-a=$patientA" "--patient-b=$patientB"
$exitCode = $LASTEXITCODE
if ($exitCode -ne 0) { exit $exitCode }
