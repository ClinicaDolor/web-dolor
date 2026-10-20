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
$migrationScript = Join-Path $root 'tests\patient-interview\m07-shared-canonical-migration.php'
$canonicalIntegration = Join-Path $root 'tests\patient-interview\m07-shared-canonical-integration.php'
$legacyIntegration = Join-Path $root 'tests\patient-interview\m07-mysql-integration.php'
$migrationSnapshotPath = Join-Path ([IO.Path]::GetTempPath()) ("m07-canonical-$([Guid]::NewGuid().ToString('N')).snapshot.json")
$snapshotPath = Join-Path ([IO.Path]::GetTempPath()) ("m07-p04-medium-$([Guid]::NewGuid().ToString('N')).snapshot.json")
$snapshotCreated = $false
$restoration = $null
$safetyPreflight = $null
$p04 = $null
$readOnlyPreflight = $null
$migration = $null
$canonical = $null
$legacy = $null
$staticContract = $null
$p09Contract = $null
$fatalError = $null

if (-not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) { throw 'No se encontró el ejecutable PHP indicado.' }
foreach ($required in @($fixtureHelper, $migrationScript, $canonicalIntegration, $legacyIntegration)) {
    if (-not (Test-Path -LiteralPath $required -PathType Leaf)) { throw "Falta un arnés requerido: $required" }
}

function Invoke-JsonPhp {
    param([string]$ScriptPath, [string[]]$Arguments)
    $raw = @(& $PhpPath $ScriptPath @Arguments 2>$null)
    $exitCode = $LASTEXITCODE
    try { $json = (($raw -join "`n") | ConvertFrom-Json) } catch { throw "El arnés PHP no devolvió JSON controlado: $ScriptPath" }
    [PSCustomObject]@{ ExitCode = $exitCode; Json = $json }
}

function Invoke-TextProcess {
    param([string]$FilePath, [string[]]$Arguments)
    try {
        $raw = @(& $FilePath @Arguments 2>$null)
        return [PSCustomObject]@{ ExitCode = $LASTEXITCODE; Output = ($raw -join "`n") }
    } catch {
        return [PSCustomObject]@{ ExitCode = 2; Output = '' }
    }
}

try {
    $integrity = (& whoami.exe /groups /fo csv /nh 2>$null | Out-String)
    if ($integrity -notmatch '(?i)Medium Mandatory Level') { throw 'El ejecutor debe iniciarse desde PowerShell normal con integridad Medium.' }

    # Read-only safety checks precede the credential fixture writes.
    $safetyPreflight = Invoke-JsonPhp $fixtureHelper @('--action=preflight', "--root=$root", "--database=$database", "--snapshot=$snapshotPath")
    if ($safetyPreflight.ExitCode -ne 0 -or -not $safetyPreflight.Json.ok -or $safetyPreflight.Json.database_effective -ne $database) {
        throw 'El preflight de conexión, InnoDB, duplicados u huérfanos falló; no se aplica DDL.'
    }

    $prepare = Invoke-JsonPhp $fixtureHelper @('--action=prepare', "--root=$root", "--database=$database", "--snapshot=$snapshotPath")
    if ($prepare.ExitCode -ne 0 -or -not $prepare.Json.ok -or $prepare.Json.database_effective -ne $database) { throw 'El snapshot P04 falló antes de escribir.' }
    $snapshotCreated = $true

    $create = Invoke-JsonPhp $fixtureHelper @('--action=create', "--root=$root", "--database=$database", "--snapshot=$snapshotPath")
    if ($create.ExitCode -ne 0 -or -not $create.Json.ok -or -not $create.Json.independent_active_credentials) { throw 'No se pudieron preparar las credenciales sintéticas independientes.' }

    $p04 = Invoke-JsonPhp (Join-Path $root 'tests\p04\verify-isolation.php') @("--database=$database", "--patient-a=$patientA", "--patient-b=$patientB", '--isolation-mode=cross-clinic', '--json')
    if ($p04.ExitCode -ne 0 -or -not $p04.Json.ok) { throw 'P04 no pasó; se detiene antes del preflight M07 y del DDL.' }

    $readOnlyPreflight = Invoke-JsonPhp $migrationScript @('--mode=preflight', "--database=$database", "--patient-a=$patientA", "--patient-b=$patientB")
    if ($readOnlyPreflight.ExitCode -ne 0 -or [string]$readOnlyPreflight.Json.status -ne 'PASS_READ_ONLY_PREFLIGHT') { throw 'El preflight M07 no pasó; no se aplica DDL.' }

    $migration = Invoke-JsonPhp $migrationScript @('--mode=apply', '--allow-synthetic-write', "--database=$database", "--patient-a=$patientA", "--patient-b=$patientB", "--snapshot=$migrationSnapshotPath")
    $migrationStatus = [string]$migration.Json.status
    if ($migration.ExitCode -ne 0 -or $migrationStatus -notin @('PASS_APPLIED_SYNTHETIC', 'PASS_ALREADY_APPLIED_SYNTHETIC')) {
        throw 'La migración sintética no terminó en un estado aceptado; se conserva su snapshot para recuperación controlada.'
    }

    $canonical = Invoke-JsonPhp $canonicalIntegration @('--allow-synthetic-write', "--database=$database", "--patient-a=$patientA", "--patient-b=$patientB")
    if ($canonical.ExitCode -ne 0 -or [string]$canonical.Json.status -ne 'PASS_SYNTHETIC_CANONICAL_SERVICE' -or [string]$canonical.Json.restoration -ne 'PASS_FOCAL') {
        throw 'La integración MySQL del servicio canónico o su restauración focal falló.'
    }

    $legacy = Invoke-JsonPhp $legacyIntegration @('--allow-synthetic-write', "--database=$database", "--patient-a=$patientA", "--patient-b=$patientB")
    if ($legacy.ExitCode -ne 0 -or [string]$legacy.Json.status -notin @('PASS_MYSQL_SYNTHETIC', 'PASS')) {
        throw 'La regresión MySQL del adaptador conversacional no pasó.'
    }

    $staticContract = Invoke-TextProcess $PhpPath @((Join-Path $root 'tests\patient-interview\m07-conversational-implementation-contract.php'))
    $p09Contract = Invoke-TextProcess 'node' @((Join-Path $root 'tests\p09\contract-check.mjs'))
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
    $preflightStatus = if ($null -ne $readOnlyPreflight -and $readOnlyPreflight.ExitCode -eq 0 -and [string]$readOnlyPreflight.Json.status -eq 'PASS_READ_ONLY_PREFLIGHT') { 'PASS_READ_ONLY_PREFLIGHT' } else { 'FAIL_OR_NOT_REACHED' }
    $migrationStatus = if ($null -ne $migration) { [string]$migration.Json.status } else { 'NOT_EXECUTED' }
    $migrationSnapshotRetained = Test-Path -LiteralPath $migrationSnapshotPath
    $canonicalStatus = if ($null -ne $canonical -and $canonical.ExitCode -eq 0 -and [string]$canonical.Json.status -eq 'PASS_SYNTHETIC_CANONICAL_SERVICE' -and [string]$canonical.Json.restoration -eq 'PASS_FOCAL') { 'PASS' } else { 'FAIL_OR_NOT_REACHED' }
    $legacyStatus = if ($null -ne $legacy -and $legacy.ExitCode -eq 0 -and [string]$legacy.Json.status -eq 'PASS_SYNTHETIC_MYSQL_FOCAL') { 'PASS' } else { 'FAIL_OR_NOT_REACHED' }
    $staticStatus = if ($null -ne $staticContract -and $staticContract.ExitCode -eq 0) { 'PASS' } else { 'FAIL_OR_NOT_REACHED' }
    $p09Status = if ($null -ne $p09Contract -and $p09Contract.ExitCode -eq 0) { 'PASS' } else { 'FAIL_OR_NOT_REACHED' }

    $allPassed = $restoredOk -and $p04Status -eq 'PASS' -and $preflightStatus -eq 'PASS_READ_ONLY_PREFLIGHT' -and $migrationStatus -in @('PASS_APPLIED_SYNTHETIC', 'PASS_ALREADY_APPLIED_SYNTHETIC') -and $canonicalStatus -eq 'PASS' -and $legacyStatus -eq 'PASS' -and $staticStatus -eq 'PASS' -and $p09Status -eq 'PASS'
    if ($allPassed) { $final = 'PASS_SYNTHETIC_CANONICAL_VERIFIED' }
    elseif (-not $restoredOk -or ($migrationSnapshotRetained -and $migrationStatus -notin @('PASS_APPLIED_SYNTHETIC', 'PASS_ALREADY_APPLIED_SYNTHETIC'))) { $final = 'FAIL_REQUIRES_RECOVERY' }
    else { $final = 'PARTIAL_WITH_BLOCKERS' }

    [ordered]@{
        PHASE = 'M07_SHARED_CANONICAL_SYNTHETIC_APPLY'
        MYSQL_CONNECTION = if ($null -ne $safetyPreflight -and $safetyPreflight.ExitCode -eq 0) { 'PASS_LOCAL_PDO' } else { 'NOT_VERIFIED' }
        MYSQL_VERSION = if ($null -ne $readOnlyPreflight) { [string]$readOnlyPreflight.Json.mysql_version } else { 'NOT_VERIFIED' }
        SYNTHETIC_DATABASE_VERIFIED = if ($null -ne $safetyPreflight -and $safetyPreflight.Json.database_effective -eq $database) { 'PASS' } else { 'NOT_VERIFIED' }
        P04_GUARD = $p04Status
        READ_ONLY_PREFLIGHT = $preflightStatus
        MIGRATION_APPLIED = if ($migrationStatus -in @('PASS_APPLIED_SYNTHETIC', 'PASS_ALREADY_APPLIED_SYNTHETIC')) { 'PASS' } else { $migrationStatus }
        MIGRATION_REEXECUTION_SAFETY = if ($migrationStatus -eq 'PASS_ALREADY_APPLIED_SYNTHETIC') { 'PASS_IDEMPOTENT' } elseif ($migrationStatus -eq 'PASS_APPLIED_SYNTHETIC') { 'PASS_FIRST_APPLY' } else { 'NOT_VERIFIED' }
        M07_CANONICAL_SERVICE = $canonicalStatus
        TRADITIONAL_P09_REGRESSION = $legacyStatus
        CONVERSATIONAL_M07_REGRESSION = $staticStatus
        M07_SHARED_PDO_REGRESSION = $p09Status
        FIXTURE_SNAPSHOT = if ($snapshotCreated) { 'PASS_FOCAL_ACCESS_ROWS' } else { 'NOT_CREATED' }
        FIXTURE_RESTORATION = if ($restoredOk) { 'PASS' } else { 'FAIL' }
        RESTORATION_HASH_VERIFIED = if ($restoredOk) { 'PASS' } else { 'FAIL' }
        TEMPORARY_CREDENTIALS_REMAIN = if ($restoredOk) { 'NO' } else { 'UNKNOWN_RESTORE_BLOCKED' }
        SCHEMA_SYNTHETIC_REMAINS_MIGRATED = if ($migrationStatus -in @('PASS_APPLIED_SYNTHETIC', 'PASS_ALREADY_APPLIED_SYNTHETIC')) { 'YES' } else { 'NO_OR_UNKNOWN' }
        MIGRATION_SNAPSHOT_RETAINED = if ($migrationSnapshotRetained) { 'YES' } else { 'NO' }
        M07CanonicalActivationGate = 'CLOSED_IN_APPLICATION'
        NEW_CLINICAL_WRITES_ENABLED = 'NO'
        M07_FINALIZATION_ENABLED = 'NO'
        REAL_BROWSER_ACCEPTANCE = 'PENDING'
        DDL_ENABLED = 'ONLY_SYNTHETIC_EXPLICIT_APPLY'
        BACKFILL_ENABLED = 'ONLY_SYNTHETIC_EXPLICIT_APPLY'
        DATABASE_WRITES_BY_CODEX = 'NO'
        MANUAL_COMMAND = '& .\tests\patient-interview\run-m07-shared-apply-medium.ps1'
        FINAL_STATUS = $final
    } | ConvertTo-Json -Depth 5

    if ($final -ne 'PASS_SYNTHETIC_CANONICAL_VERIFIED') { exit 2 }
}
