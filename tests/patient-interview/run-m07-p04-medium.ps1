[CmdletBinding()]
param(
    [string]$PhpPath = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$database = 'clinica_dolor_demo_20261010_final'
$patientA = 9301
$patientB = 9302
$fixtureHelper = Join-Path $root 'tests\patient-interview\m07-p04-medium-fixture.php'
$snapshotPath = Join-Path ([IO.Path]::GetTempPath()) ("m07-p04-medium-$([Guid]::NewGuid().ToString('N')).snapshot.json")
$snapshotCreated = $false
$restoration = $null
$p04 = $null
$preflight = $null
$fatalError = $null

if (-not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) { throw 'No se encontró el ejecutable PHP indicado.' }
if (-not (Test-Path -LiteralPath $fixtureHelper -PathType Leaf)) { throw 'Falta el helper sintético compartido.' }

function Invoke-JsonPhp {
    param([string]$ScriptPath, [string[]]$Arguments)
    $raw = @(& $PhpPath $ScriptPath @Arguments 2>$null)
    $exitCode = $LASTEXITCODE
    try { $json = (($raw -join "`n") | ConvertFrom-Json) } catch { throw 'El ejecutor PHP no devolvió JSON controlado.' }
    [PSCustomObject]@{ ExitCode = $exitCode; Json = $json }
}

try {
    $integrity = (& whoami.exe /groups /fo csv /nh 2>$null | Out-String)
    if ($integrity -notmatch '(?i)Medium Mandatory Level') { throw 'El ejecutor debe iniciarse desde PowerShell normal con integridad Medium.' }

    $prepare = Invoke-JsonPhp $fixtureHelper @('--action=prepare', "--root=$root", "--database=$database", "--snapshot=$snapshotPath")
    if ($prepare.ExitCode -ne 0 -or -not $prepare.Json.ok -or $prepare.Json.database_effective -ne $database) { throw 'El snapshot/preflight sintético falló antes de escribir.' }
    $snapshotCreated = $true

    $create = Invoke-JsonPhp $fixtureHelper @('--action=create', "--root=$root", "--database=$database", "--snapshot=$snapshotPath")
    if ($create.ExitCode -ne 0 -or -not $create.Json.ok -or -not $create.Json.independent_active_credentials) { throw 'No se pudieron preparar dos credenciales sintéticas independientes.' }

    $p04 = Invoke-JsonPhp (Join-Path $root 'tests\p04\verify-isolation.php') @("--database=$database", "--patient-a=$patientA", "--patient-b=$patientB", '--isolation-mode=cross-clinic', '--json')
    $preflight = Invoke-JsonPhp (Join-Path $root 'tests\patient-interview\m07-shared-canonical-migration.php') @('--mode=preflight', "--database=$database", "--patient-a=$patientA", "--patient-b=$patientB")
} catch {
    $fatalError = $_.Exception.Message
} finally {
    if ($snapshotCreated) {
        try { $restoration = Invoke-JsonPhp $fixtureHelper @('--action=restore', "--root=$root", "--database=$database", "--snapshot=$snapshotPath") }
        catch { $restoration = [PSCustomObject]@{ ExitCode = 2; Json = [PSCustomObject]@{ ok = $false; restoration_hash_verified = $false } } }
    }

    $restoredOk = $null -ne $restoration -and $restoration.ExitCode -eq 0 -and [bool]$restoration.Json.ok -and [bool]$restoration.Json.restoration_hash_verified
    if ($restoredOk -and (Test-Path -LiteralPath $snapshotPath)) { Remove-Item -LiteralPath $snapshotPath -Force -ErrorAction SilentlyContinue }

    $p04Status = if ($null -ne $p04 -and $p04.ExitCode -eq 0 -and [bool]$p04.Json.ok) { 'PASS' } else { 'FAIL_OR_NOT_REACHED' }
    $preflightStatus = if ($null -ne $preflight -and $preflight.ExitCode -eq 0 -and [string]$preflight.Json.status -eq 'PASS_READ_ONLY_PREFLIGHT') { 'PASS_READ_ONLY_PREFLIGHT' } else { 'FAIL_OR_NOT_REACHED' }
    $final = if ($restoredOk -and $p04Status -eq 'PASS' -and $preflightStatus -eq 'PASS_READ_ONLY_PREFLIGHT') { 'PASS_P04_AND_PREFLIGHT_RESTORED' } elseif (-not $restoredOk) { 'BLOCKED_RESTORATION_FAILED_SNAPSHOT_RETAINED' } else { 'BLOCKED_P04_OR_PREFLIGHT' }

    [ordered]@{
        PHASE = 'M07_MANUAL_MEDIUM_P04_RUNNER'
        RUNNER_PREPARED = 'YES'
        MYSQL_EXECUTION_FROM_CODEX = 'NOT_REQUIRED'
        P04_FIXTURE_MECHANISM_REUSED = 'm07-p04-medium-fixture.php -> ClinicaModel::insertPacientePin'
        SNAPSHOT_PROTECTION = if ($snapshotCreated) { 'PASS_FOCAL_ACCESS_ROWS' } else { 'NOT_CREATED' }
        RESTORATION_PROTECTION = if ($restoredOk) { 'PASS_HASH_VERIFIED' } else { 'BLOCKED_SNAPSHOT_RETAINED' }
        P04_GUARD = $p04Status
        M07_READ_ONLY_PREFLIGHT = $preflightStatus
        DDL_ENABLED = 'NO'
        BACKFILL_ENABLED = 'NO'
        DATABASE_WRITES_BY_CODEX = 'NO'
        TEMPORARY_CREDENTIALS_REMAIN = if ($restoredOk) { 'NO' } else { 'UNKNOWN_RESTORE_BLOCKED' }
        MANUAL_COMMAND = '& .\tests\patient-interview\run-m07-p04-medium.ps1'
        FINAL_STATUS = $final
    } | ConvertTo-Json -Depth 4

    if ($final -ne 'PASS_P04_AND_PREFLIGHT_RESTORED') { exit 2 }
}
