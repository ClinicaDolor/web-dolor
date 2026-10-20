[CmdletBinding()]
param(
    [string]$PhpPath = 'C:\xampp\php\php.exe',
    [ValidateSet('ValidateExisting')]
    [string]$Mode = 'ValidateExisting',
    [switch]$AllowSyntheticWrites,
    [switch]$PendingDmlOnly,
    [string]$ApprovedBaselinePath = 'C:\Users\silvi\AppData\Local\ClinicaDolor\M07-baselines\m07-clinical-baseline-20261019T202549Z-91820e37.json',
    [switch]$FocalProcessTest
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$database = 'clinica_dolor_demo_20261010_final'
$patientA = 9301
$patientB = 9302
$validator = Join-Path $root 'tests\patient-interview\m07-shared-canonical-validate-existing.php'
$fixtureHelper = Join-Path $root 'tests\patient-interview\m07-p04-medium-fixture.php'
$migrationScript = Join-Path $root 'tests\patient-interview\m07-shared-canonical-migration.php'
$sharedContract = Join-Path $root 'tests\patient-interview\m07-shared-canonical-contract.php'
$canonicalIntegration = Join-Path $root 'tests\patient-interview\m07-shared-canonical-integration.php'
$legacyIntegration = Join-Path $root 'tests\patient-interview\m07-mysql-integration.php'
$snapshotPath = Join-Path ([IO.Path]::GetTempPath()) ("m07-p04-validate-$([Guid]::NewGuid().ToString('N')).snapshot.json")
$snapshotCreated = $false
$restoration = $null
$schema = $null
$p04 = $null
$canonical = $null
$legacy = $null
$sharedContractResult = $null
$staticContract = $null
$p09Contract = $null
$firstFailedStep = $null
$firstFailedSubstep = $null
$firstFailedScript = $null
$firstFailedLine = $null
$firstErrorType = $null
$firstError = $null
$firstExitCode = $null
$firstErrorOriginalClass = $null
$firstErrorSqlState = $null
$firstErrorDriverCode = $null
$firstErrorTable = $null
$firstErrorConstraint = $null
$lastCompletedCheckpoint = 'startup'
$currentCheckpoint = 'startup'
$readOnlyOnly = $false
$schemaStatus = 'NOT_REACHED'
$fixturePreflightStatus = 'NOT_REACHED'
$fixtureSnapshotStatus = 'NOT_REACHED'
$fixtureCredentialStatus = 'NOT_REACHED'
$p04Status = 'NOT_REACHED'
$canonicalStatus = 'NOT_REACHED'
$legacyStatus = 'NOT_REACHED'
$sharedContractStatus = 'NOT_REACHED'
$staticStatus = 'NOT_REACHED'
$p09Status = 'NOT_REACHED'

function Register-Failure {
    param(
        [string]$Step,
        [string]$Message,
        [int]$ExitCode = 2,
        [string]$Substep = 'unspecified',
        [string]$ScriptPath = '',
        [int]$LineNumber = 0,
        [string]$ErrorType = 'RunnerError',
        [string]$OriginalClass = '',
        [string]$SqlState = '',
        [string]$DriverCode = '',
        [string]$TableName = '',
        [string]$ConstraintName = ''
    )
    if ($script:firstFailedStep -eq $null) {
        $script:firstFailedStep = $Step
        $script:firstFailedSubstep = $Substep
        $script:firstFailedScript = $ScriptPath
        $script:firstFailedLine = $LineNumber
        $script:firstErrorType = $ErrorType
        $script:firstError = $Message
        $script:firstExitCode = $ExitCode
        $script:firstErrorOriginalClass = $OriginalClass
        $script:firstErrorSqlState = $SqlState
        $script:firstErrorDriverCode = $DriverCode
        $script:firstErrorTable = $TableName
        $script:firstErrorConstraint = $ConstraintName
    }
}

function Get-SafeExceptionMessage {
    param([object]$Exception)
    $message = [string]$Exception.Message
    if ($message -match '(?i)no se reconoce como nombre de un cmdlet|not recognized as the name of a cmdlet') {
        return 'Comando PowerShell no reconocido durante la clasificación de estado.'
    }
    if ($message -match '(?i)return|if') {
        return 'Excepción de compatibilidad en la clasificación de estado de PowerShell.'
    }
    return 'Excepción PowerShell no clasificada; revisar tipo y línea reportados.'
}

function Get-SafeProcessMessage {
    param([string]$StdOut, [string]$StdErr, [int]$ExitCode, [string]$Step)
    $combined = (($StdOut + "`n" + $StdErr).ToLowerInvariant())
    $payload = $null
    try { $payload = ($StdOut | ConvertFrom-Json) } catch { $payload = $null }
    if ($null -ne $payload -and [string]$payload.connection_failure_category -ne '') {
        switch ([string]$payload.connection_failure_category) {
            'driver_missing' { return 'Driver PDO MySQL no disponible.' }
            'parameters_missing' { return 'Parámetros de conexión local incompletos.' }
            'parameters_invalid' { return 'Parámetros de conexión fuera de la allowlist local.' }
            'authentication_rejected' { return 'Autenticación MySQL rechazada.' }
            'database_incorrect' { return 'La base efectiva no coincide con la base sintética autorizada.' }
            'server_unavailable' { return 'Servidor MySQL local no disponible.' }
            'post_connection_sql' { return 'Error SQL posterior a una conexión PDO exitosa.' }
        }
    }
    if ($null -ne $payload -and [string]$payload.connection_stage -eq 'post_connection_sql') {
        return 'Error SQL posterior a una conexión PDO exitosa.'
    }
    if ($null -ne $payload -and [string]$payload.first_error_class -ne '' -and [string]$payload.first_error_class -ne 'NONE') {
        $failedTest = [string]$payload.first_failed_test
        $safeMessage = [string]$payload.first_error_message_sanitized
        $diagnostic = @()
        if ([string]$payload.first_error_sqlstate -and [string]$payload.first_error_sqlstate -ne 'NONE') { $diagnostic += "SQLSTATE=$([string]$payload.first_error_sqlstate)" }
        if ([string]$payload.first_error_driver_code -and [string]$payload.first_error_driver_code -ne 'NONE') { $diagnostic += "DRIVER_CODE=$([string]$payload.first_error_driver_code)" }
        if ([string]$payload.first_error_table -match '^[A-Za-z][A-Za-z0-9_]*$') { $diagnostic += "TABLE=$([string]$payload.first_error_table)" }
        if ([string]$payload.first_error_constraint -match '^[A-Za-z][A-Za-z0-9_]*$') { $diagnostic += "CONSTRAINT=$([string]$payload.first_error_constraint)" }
        $suffix = if ($diagnostic.Count -gt 0) { ' [' + ($diagnostic -join '; ') + ']' } else { '' }
        if ([string]$payload.first_error_class -match '^M07CanonicalException') { return "Fallo de contrato M07: $safeMessage$suffix" }
        if ([string]$payload.first_error_class -eq 'RuntimeException') { return "Aserción M07 fallida: $failedTest" }
        if ([string]$payload.first_error_class -eq 'PDOException') { return "Error PDO posterior a la conexión$suffix." }
        if ([string]$payload.first_error_class -eq 'RestorationError') { return 'La restauración clínica focal del hijo falló.' }
    }
    if ($combined -match 'could not find driver') { return 'Driver PDO MySQL no disponible.' }
    if ($combined -match 'connection refused|connect timed out|mysql server has gone away|can''t connect|cannot connect') { return 'Servidor MySQL local no disponible.' }
    if ($combined -match 'access denied|authentication|1045') { return 'Autenticación MySQL rechazada.' }
    if ($combined -match 'unknown database|1049|base no coincide') { return 'La base efectiva no coincide con la base sintética autorizada.' }
    if ($combined -match 'sqlstate|unknown column|table .*doesn''t exist|sql error') { return 'Error SQL posterior a una conexión PDO exitosa.' }
    if ($combined -match 'permission|access denied|low integrity') { return 'Permiso de ejecución o conexión rechazado.' }
    if ($combined -match 'parse error|syntax error') { return 'Error de sintaxis en el arnés.' }
    if ($combined -match 'json|controlado') { return 'El arnés no devolvió JSON controlado.' }
    return "El paso $Step terminó con error técnico no clasificado."
}

function Get-SafeJsonProtocolFailure {
    param([string]$StdOut, [string]$StdErr, [int]$ExitCode)
    $stdoutState = if ([string]::IsNullOrWhiteSpace($StdOut)) { 'EMPTY' } else { 'NONEMPTY_INVALID_OR_MULTIPLE' }
    $stderrState = if ([string]::IsNullOrWhiteSpace($StdErr)) { 'EMPTY' } else { 'NONEMPTY_SANITIZED' }
    return "El proceso hijo no emitió un único JSON válido; STDOUT=$stdoutState; STDERR=$stderrState; EXIT_CODE=$ExitCode; SQLSTATE=NOT_VERIFIED."
}

function ConvertTo-NativeArgument {
    param([AllowNull()][string]$Value)
    if ($null -eq $Value -or $Value.Length -eq 0) { return '""' }
    if ($Value -notmatch '[\s"]') { return $Value }
    $escaped = $Value -replace '(\\*)"', '$1$1\"'
    $escaped = $escaped -replace '(\\+)$', '$1$1'
    return '"' + $escaped + '"'
}

function Invoke-NativeProcess {
    param([string]$FilePath, [string[]]$Arguments)
    $startInfo = New-Object System.Diagnostics.ProcessStartInfo
    $startInfo.FileName = $FilePath
    $startInfo.Arguments = (($Arguments | ForEach-Object { ConvertTo-NativeArgument ([string]$_) }) -join ' ')
    $startInfo.UseShellExecute = $false
    $startInfo.CreateNoWindow = $true
    $startInfo.RedirectStandardOutput = $true
    $startInfo.RedirectStandardError = $true
    $process = New-Object System.Diagnostics.Process
    $process.StartInfo = $startInfo
    try {
        if (-not $process.Start()) { throw 'No fue posible iniciar el proceso hijo.' }
        $stdoutTask = $process.StandardOutput.ReadToEndAsync()
        $stderrTask = $process.StandardError.ReadToEndAsync()
        $process.WaitForExit()
        [void]$stdoutTask.Wait()
        [void]$stderrTask.Wait()
        return [PSCustomObject]@{
            ExitCode = $process.ExitCode
            StdOut = $stdoutTask.Result
            StdErr = $stderrTask.Result
        }
    } finally {
        $process.Dispose()
    }
}

function Invoke-JsonPhp {
    param([string]$Step, [string]$ScriptPath, [string[]]$Arguments)
    try {
        $invocation = Invoke-NativeProcess -FilePath $PhpPath -Arguments (@($ScriptPath) + @($Arguments))
    } catch {
        $message = 'No fue posible iniciar el proceso PHP hijo.'
        Register-Failure -Step $Step -Message $message -ExitCode 2 -Substep 'process_start' -ScriptPath $ScriptPath -ErrorType 'ProcessStartError'
        throw $message
    }
    $exitCode = [int]$invocation.ExitCode
    $stdout = [string]$invocation.StdOut
    $stderr = [string]$invocation.StdErr
    try {
        if ([string]::IsNullOrWhiteSpace($stdout)) { throw 'empty_child_stdout' }
        $json = ($stdout | ConvertFrom-Json -ErrorAction Stop)
        if ($null -eq $json -or $json -is [System.Array]) { throw 'child_json_not_object' }
    } catch {
        $message = Get-SafeJsonProtocolFailure $stdout $stderr $exitCode
        Register-Failure -Step $Step -Message $message -ExitCode $exitCode -Substep 'json_parse' -ScriptPath $ScriptPath -ErrorType 'ChildJsonError'
        throw $message
    }
    if ($exitCode -ne 0) {
        $message = Get-SafeProcessMessage $stdout $stderr $exitCode $Step
        $childErrorType = if ([string]$json.first_error_class -and [string]$json.first_error_class -ne 'NONE') { [string]$json.first_error_class } else { 'ChildProcessExit' }
        $childSubstep = if ([string]$json.first_failed_operation -and [string]$json.first_failed_operation -ne 'NOT_REACHED') { [string]$json.first_failed_operation } else { 'child_process' }
        Register-Failure -Step $Step -Message $message -ExitCode $exitCode -Substep $childSubstep -ScriptPath $ScriptPath -ErrorType $childErrorType -OriginalClass ([string]$json.first_error_original_class) -SqlState ([string]$json.first_error_sqlstate) -DriverCode ([string]$json.first_error_driver_code) -TableName ([string]$json.first_error_table) -ConstraintName ([string]$json.first_error_constraint)
    }
    return [PSCustomObject]@{ ExitCode = $exitCode; Json = $json; StdOut = $stdout; StdErr = $stderr }
}

function Invoke-ExpectedPriorBaselineDiff {
    param([string]$PhpScript, [string]$DatabaseName, [int]$SyntheticPatientA, [int]$SyntheticPatientB, [string]$SnapshotFile)
    $invocation = Invoke-NativeProcess -FilePath $PhpPath -Arguments @($PhpScript, '--mode=forensic-diff', "--database=$DatabaseName", "--patient-a=$SyntheticPatientA", "--patient-b=$SyntheticPatientB", "--snapshot=$SnapshotFile")
    try { $json = ([string]$invocation.StdOut | ConvertFrom-Json -ErrorAction Stop) } catch { return [PSCustomObject]@{ Pass = $false; ExitCode = [int]$invocation.ExitCode; Json = $null } }
    $expected = @{
        'pac_respuestas_paciente_modulo_7' = @{ baseline = 1; current = 2; added = 1; questions = '2' }
        'pac_m07_medicamentos_no_catalogados' = @{ baseline = 0; current = 6; added = 6; questions = '8,15,22,30,37,46' }
        'pac_m07_operaciones' = @{ baseline = 0; current = 27; added = 27; questions = '' }
        'pac_m07_revision_clinica' = @{ baseline = 0; current = 0; added = 0; questions = '' }
    }
    $pass = [int]$invocation.ExitCode -eq 1 -and [string]$json.status -eq 'FORENSIC_DIFFERENCE_FOUND' -and [string]$json.database_effective -eq $DatabaseName -and [string]$json.synthetic_patients -eq '9301_9302_ONLY' -and $json.table_reports.Count -eq 4
    foreach ($tableReport in $json.table_reports) {
        $tableName = [string]$tableReport.table
        if (-not $expected.ContainsKey($tableName)) { $pass = $false; continue }
        $spec = $expected[$tableName]
        $questions = @($tableReport.affected_question_ids | ForEach-Object { [string]$_ } | Sort-Object) -join ','
        $patients = @($tableReport.affected_patient_ids | ForEach-Object { [int]$_ } | Sort-Object) -join ','
        $expectedQuestions = @($spec.questions -split ',' | Where-Object { $_ -ne '' } | Sort-Object) -join ','
        $expectedPatients = if ([int]$spec.added -gt 0) { '9301' } else { '' }
        if ([int]$tableReport.baseline_rows -ne [int]$spec.baseline -or [int]$tableReport.current_rows -ne [int]$spec.current -or [int]$tableReport.rows_added -ne [int]$spec.added -or [int]$tableReport.rows_removed -ne 0 -or [int]$tableReport.rows_modified -ne 0 -or [int]$tableReport.duplicate_keys_detected -ne 0 -or $questions -ne $expectedQuestions -or $patients -ne $expectedPatients) { $pass = $false }
    }
    return [PSCustomObject]@{ Pass = $pass; ExitCode = [int]$invocation.ExitCode; Json = $json }
}

function Invoke-TextProcess {
    param([string]$Step, [string]$FilePath, [string[]]$Arguments)
    try {
        $invocation = Invoke-NativeProcess -FilePath $FilePath -Arguments $Arguments
    } catch {
        Register-Failure -Step $Step -Message 'No fue posible iniciar el proceso hijo.' -ExitCode 2 -Substep 'process_start' -ScriptPath $FilePath -ErrorType 'ProcessStartError'
        throw 'No fue posible iniciar el proceso hijo.'
    }
    $exitCode = [int]$invocation.ExitCode
    if ($exitCode -ne 0) {
        Register-Failure -Step $Step -Message (Get-SafeProcessMessage ([string]$invocation.StdOut) ([string]$invocation.StdErr) $exitCode $Step) -ExitCode $exitCode -Substep 'child_process' -ScriptPath $FilePath -ErrorType 'ChildProcessExit'
    }
    return [PSCustomObject]@{ ExitCode = $exitCode; StdOut = [string]$invocation.StdOut; StdErr = [string]$invocation.StdErr }
}

function Get-JsonStepStatus {
    param([object]$Invocation, [string[]]$PassStatuses)
    if ($null -eq $Invocation) { return 'NOT_REACHED' }
    $status = [string]$Invocation.Json.status
    if ($Invocation.ExitCode -eq 0 -and $PassStatuses -contains $status) { return 'PASS' }
    if ($status -eq 'NOT_REACHED') { return 'NOT_REACHED' }
    $error = [string]$Invocation.Json.error
    if ($status -match '(?i)BLOCK' -or $error -match '(?i)no fue posible conectar|no disponible|conexión|conectar|pdo|local') { return 'BLOCKED' }
    return 'FAIL'
}

function Get-P04StepStatus {
    param([object]$Invocation)
    if ($null -eq $Invocation) { return 'NOT_REACHED' }
    if ($Invocation.ExitCode -eq 0 -and [bool]$Invocation.Json.ok) { return 'PASS' }
    $error = [string]$Invocation.Json.error
    if ($error -match '(?i)no fue posible conectar|no disponible|conexión|conectar|pdo|local') { return 'BLOCKED' }
    return 'FAIL'
}

function Get-TextStepStatus {
    param([object]$Invocation)
    if ($null -eq $Invocation) { return 'NOT_REACHED' }
    if ($Invocation.ExitCode -eq 0) { return 'PASS' }
    return 'FAIL'
}

function Get-RestorationStatus {
    param([object]$Invocation, [string]$PropertyName)
    if ($null -eq $Invocation) { return 'NOT_REACHED' }
    $value = [string]$Invocation.Json.$PropertyName
    if ($value -eq 'PASS_FOCAL') { return 'PASS' }
    if ([string]$Invocation.Json.status -match '(?i)BLOCK') { return 'BLOCKED' }
    return 'FAIL'
}

function Invoke-PendingDmlOnlyRun {
    $baselineTool = Join-Path $root 'tests\patient-interview\m07-durable-clinical-baseline.php'
    $pendingTests = Join-Path $root 'tests\patient-interview\m07-shared-canonical-integration.php'
    $requiredScripts = @(
        $PhpPath,
        $validator,
        $baselineTool,
        $pendingTests,
        $sharedContract,
        (Join-Path $root 'tests\patient-interview\m07-other-medication-sql-contract.php'),
        (Join-Path $root 'tests\patient-interview\m06-conversational-flow-contract.php'),
        (Join-Path $root 'tests\patient-interview\m06-omission-contract.php'),
        (Join-Path $root 'tests\patient-interview\m04-m06-functional-contract.php')
    )
    $result = [ordered]@{
        PHASE = 'M07_PENDING_DML_FINAL_INTEGRATION_V1'
        MODE = '--pending-dml-only'
        DATABASE = $database
        SERVER = 'NOT_VERIFIED'
        PORT = 'NOT_VERIFIED'
        DATABASE_GUARD = 'NOT_REACHED'
        SCHEMA_PREFLIGHT = 'NOT_REACHED'
        APPROVED_BASELINE_COMPARE_BEFORE = 'NOT_REACHED'
        PRE_RUN_SNAPSHOT = 'NOT_CREATED'
        PRE_RUN_SNAPSHOT_VERIFIED = 'NOT_REACHED'
        APPROVED_BASELINE_COMPARE_AFTER_SNAPSHOT = 'NOT_REACHED'
        PREVIOUS_ROWS_EXPECTED = 34
        PREVIOUS_ROWS_PRESERVED = 'NOT_REACHED'
        PREVIOUS_AUDIT_PRESERVED = 'NOT_REACHED'
        DML_01_IDEMPOTENCY_CONFLICT = 'NOT_REACHED'
        DML_02_STALE_VERSION = 'NOT_REACHED'
        DML_03_APPEND_ONLY_AUDIT = 'NOT_REACHED'
        DML_04_SQL_ROLLBACK = 'NOT_REACHED'
        NEW_MEDICATION_ROWS = 'NOT_REACHED'
        NEW_AUDIT_ROWS = 'NOT_REACHED'
        OTHER_NEW_ROWS = 'NOT_REACHED'
        NEW_ROW_IDS = @()
        POST_RUN_INTEGRITY = 'NOT_REACHED'
        M07_GATE = 'NOT_REACHED'
        M06_REGRESSION = 'NOT_REACHED'
        STATIC_TESTS = 'NOT_REACHED'
        MYSQL_DML = 'NOT_REACHED'
        FAILURE_STAGE = 'NONE'
        ERROR_SANITIZED = 'NONE'
        FINAL_STATUS = 'BLOCKED'
    }
    $stage = 'startup'
    $snapshotPath = ''
    $child = $null
    $childAttempted = $false
    $postDiff = $null
    $postSchema = $null
    $staticResults = @{}
    try {
        if ($AllowSyntheticWrites) { throw 'No se combina el modo acotado con el modo amplio de escrituras.' }
        foreach ($required in $requiredScripts) {
            if (-not (Test-Path -LiteralPath $required -PathType Leaf)) { throw 'Falta un ejecutable o arnés requerido.' }
        }
        if (-not (Test-Path -LiteralPath $ApprovedBaselinePath -PathType Leaf)) { throw 'Falta el baseline durable previamente aprobado.' }
        if ($PSVersionTable.PSVersion.Major -ne 5 -or $PSVersionTable.PSVersion.Minor -ne 1) { throw 'El modo acotado requiere Windows PowerShell 5.1.' }
        $approvedFullPath = (Resolve-Path -LiteralPath $ApprovedBaselinePath).Path
        $requiredApprovedPath = 'C:\Users\silvi\AppData\Local\ClinicaDolor\M07-baselines\m07-clinical-baseline-20261019T202549Z-91820e37.json'
        if (-not [string]::Equals($approvedFullPath, $requiredApprovedPath, [StringComparison]::OrdinalIgnoreCase)) { throw 'El modo acotado sólo admite el baseline aprobado explícito.' }
        $integrity = (& whoami.exe /groups /fo csv /nh 2>$null | Out-String)
        if ($integrity -notmatch '(?i)Medium Mandatory Level') { throw 'El modo de escritura requiere PowerShell normal con integridad Medium.' }

        $stage = 'gate_closed_static_contract'
        $gateContract = Invoke-TextProcess $stage $PhpPath @($sharedContract)
        if ($gateContract.ExitCode -ne 0) { throw 'El contrato estático no confirma que M07 permanezca cerrado.' }
        $result.M07_GATE = 'CLOSED_STATIC_CONTRACT_PASS'

        $stage = 'read_only_schema_validation'
        $schemaResult = Invoke-JsonPhp $stage $validator @("--database=$database", "--patient-a=$patientA", "--patient-b=$patientB")
        if ($schemaResult.ExitCode -ne 0 -or [string]$schemaResult.Json.status -ne 'PASS_READ_ONLY_EXISTING_SCHEMA' -or [string]$schemaResult.Json.database_effective -ne 'PASS' -or [string]$schemaResult.Json.schema_integrity -ne 'PASS' -or [string]$schemaResult.Json.clinical_state_integrity -ne 'PASS') { throw 'El preflight read-only de esquema e integridad no pasó.' }
        $result.SCHEMA_PREFLIGHT = 'PASS_READ_ONLY_EXISTING_SCHEMA'
        $result.DATABASE_GUARD = 'PASS_EFFECTIVE_DATABASE_EXACT'
        $result.SERVER = 'PC'
        $result.PORT = 3306

        $stage = 'approved_baseline_expected_prior_delta_before'
        $oldDiff = Invoke-ExpectedPriorBaselineDiff $baselineTool $database $patientA $patientB $approvedFullPath
        if (-not $oldDiff.Pass) { throw 'El estado previo no coincide con las 34 diferencias sintéticas previamente autorizadas.' }
        $result.APPROVED_BASELINE_COMPARE_BEFORE = 'PASS_EXACT_PREVIOUS_34_ROW_DELTA'

        $stage = 'pre_run_snapshot_create'
        $snapshotResult = Invoke-JsonPhp $stage $baselineTool @('--mode=create', "--database=$database", "--patient-a=$patientA", "--patient-b=$patientB")
        if ($snapshotResult.ExitCode -ne 0 -or [string]$snapshotResult.Json.status -ne 'PASS_DURABLE_BASELINE_CREATED' -or [string]$snapshotResult.Json.database_effective -ne $database -or [string]$snapshotResult.Json.consistent_snapshot -ne 'PASS_DOUBLE_READ_SAME_TRANSACTION' -or [string]$snapshotResult.Json.snapshot_file_readback -ne 'PASS') { throw 'No se pudo verificar un snapshot pre-run durable.' }
        $snapshotPath = [string]$snapshotResult.Json.snapshot_path
        if ([string]::IsNullOrWhiteSpace($snapshotPath) -or -not (Test-Path -LiteralPath $snapshotPath -PathType Leaf)) { throw 'El archivo del snapshot pre-run no está disponible.' }
        $result.PRE_RUN_SNAPSHOT = $snapshotPath
        $result.PRE_RUN_SNAPSHOT_VERIFIED = 'PASS_FULL_ROWS_MANIFEST_READBACK'

        $stage = 'approved_baseline_expected_prior_delta_after_snapshot'
        $oldDiffAfterSnapshot = Invoke-ExpectedPriorBaselineDiff $baselineTool $database $patientA $patientB $approvedFullPath
        if (-not $oldDiffAfterSnapshot.Pass) { throw 'El estado cambió durante la creación del snapshot; no se iniciará DML.' }
        $result.APPROVED_BASELINE_COMPARE_AFTER_SNAPSHOT = 'PASS_EXACT_PREVIOUS_34_ROW_DELTA'

        $stage = 'pending_dml_only'
        $childAttempted = $true
        $child = Invoke-JsonPhp $stage $pendingTests @('--allow-synthetic-write', '--pending-dml-only', "--database=$database", "--patient-a=$patientA", "--patient-b=$patientB", "--snapshot=$snapshotPath")
        $testNames = @{
            DML_01_IDEMPOTENCY_CONFLICT = 'other_medication_idempotency_payload_conflict'
            DML_02_STALE_VERSION = 'other_medication_stale_version_conflict'
            DML_03_APPEND_ONLY_AUDIT = 'append_only_operation_audit'
            DML_04_SQL_ROLLBACK = 'other_medication_atomic_rollback'
        }
        foreach ($key in $testNames.Keys) {
            $result[$key] = if ([string]$child.Json.tests.($testNames[$key]) -eq 'PASS') { 'PASS' } elseif ([string]$child.Json.tests.($testNames[$key]) -eq 'FAIL') { 'FAIL' } else { 'NOT_REACHED' }
        }
        $result.PREVIOUS_ROWS_PRESERVED = if ([string]$child.Json.original_baseline_preserved -eq 'PASS_PRE_RUN_SNAPSHOT_ROWS_UNCHANGED' -and [string]$child.Json.authorized_synthetic_rows_retained -eq 'PASS' -and [string]$child.Json.previous_rows_verified -eq '34') { 'PASS' } else { 'FAIL' }
        $result.PREVIOUS_AUDIT_PRESERVED = if ([string]$child.Json.authorized_synthetic_rows_retained -eq 'PASS' -and [string]$child.Json.original_baseline_preserved -eq 'PASS_PRE_RUN_SNAPSHOT_ROWS_UNCHANGED') { 'PASS' } else { 'FAIL' }
        $result.NEW_MEDICATION_ROWS = [string]$child.Json.new_medication_rows
        $result.NEW_AUDIT_ROWS = [string]$child.Json.new_audit_rows
        $result.OTHER_NEW_ROWS = [string]$child.Json.other_new_rows
        $result.NEW_ROW_IDS = @($child.Json.retained_test_records | Where-Object { $_.change -eq 'CREATED' } | ForEach-Object { [PSCustomObject]@{ table = $_.table; id = $_.id } })
        $result.MYSQL_DML = if ($child.ExitCode -eq 0) { 'PASS' } elseif ($child.ExitCode -eq 2) { 'BLOCKED' } else { 'FAIL' }
        if ($child.ExitCode -ne 0 -or [string]$child.Json.status -ne 'PASS_PENDING_SYNTHETIC_DML_CASES' -or [string]$child.Json.pending_dml_write_budget -ne 'PASS_ONE_MEDICATION_ONE_AUDIT_NO_OTHER_ROWS' -or [string]$result.PREVIOUS_ROWS_PRESERVED -ne 'PASS') { throw 'La integración DML limitada no pasó todos los contratos.' }
    } catch {
        $result.FAILURE_STAGE = $stage
        $result.ERROR_SANITIZED = switch ($stage) {
            'read_only_schema_validation' { 'Preflight read-only de esquema o integridad fallido.' }
            'approved_baseline_compare_before' { 'El estado previo difiere del baseline aprobado.' }
            'pre_run_snapshot_create' { 'Snapshot durable pre-run no verificado.' }
            'approved_baseline_compare_after_snapshot' { 'Diferencia detectada antes de DML; ejecución detenida.' }
            'pending_dml_only' { 'Una o más pruebas DML limitadas fallaron o fueron bloqueadas.' }
            default { 'Precondición del ejecutor no satisfecha.' }
        }
    } finally {
        if ($childAttempted -and -not [string]::IsNullOrWhiteSpace($snapshotPath) -and (Test-Path -LiteralPath $snapshotPath -PathType Leaf)) {
            try {
                $stage = 'post_run_forensic_diff'
                $diffInvocation = Invoke-NativeProcess -FilePath $PhpPath -Arguments @($baselineTool, '--mode=forensic-diff', "--database=$database", "--patient-a=$patientA", "--patient-b=$patientB", "--snapshot=$snapshotPath")
                $postDiff = ([string]$diffInvocation.StdOut | ConvertFrom-Json -ErrorAction Stop)
                $result.POST_RUN_FORENSIC_DIFF_EXIT_CODE = [int]$diffInvocation.ExitCode
                $expectedByTable = @{
                    'pac_respuestas_paciente_modulo_7' = 0
                    'pac_m07_medicamentos_no_catalogados' = 1
                    'pac_m07_operaciones' = 1
                    'pac_m07_revision_clinica' = 0
                }
                $diffMatches = $true
                foreach ($tableReport in $postDiff.table_reports) {
                    if (-not $expectedByTable.ContainsKey([string]$tableReport.table) -or [int]$tableReport.rows_added -ne $expectedByTable[[string]$tableReport.table] -or [int]$tableReport.rows_removed -ne 0 -or [int]$tableReport.rows_modified -ne 0 -or [int]$tableReport.duplicate_keys_detected -ne 0) { $diffMatches = $false }
                }
                $result.POST_RUN_INTEGRITY = if ($diffMatches -and $postDiff.table_reports.Count -eq 4) { 'PASS_EXPECTED_ONE_MEDICATION_ONE_AUDIT' } else { 'FAIL_UNEXPECTED_POST_RUN_DELTA' }
            } catch {
                $result.POST_RUN_INTEGRITY = 'FAIL_NOT_VERIFIABLE'
                if ($result.FAILURE_STAGE -eq 'NONE') { $result.FAILURE_STAGE = 'post_run_forensic_diff'; $result.ERROR_SANITIZED = 'No se pudo completar la comparación forense de solo lectura.' }
            }
            try {
                $stage = 'post_run_schema_validation'
                $postSchema = Invoke-JsonPhp $stage $validator @("--database=$database", "--patient-a=$patientA", "--patient-b=$patientB")
                $result.POST_RUN_SCHEMA_PREFLIGHT = if ($postSchema.ExitCode -eq 0 -and [string]$postSchema.Json.status -eq 'PASS_READ_ONLY_EXISTING_SCHEMA' -and [string]$postSchema.Json.database_effective -eq 'PASS' -and [string]$postSchema.Json.schema_integrity -eq 'PASS' -and [string]$postSchema.Json.clinical_state_integrity -eq 'PASS') { 'PASS' } else { 'FAIL' }
            } catch {
                $result.POST_RUN_SCHEMA_PREFLIGHT = 'FAIL_NOT_VERIFIABLE'
            }
        }
        foreach ($contractPath in @(
            $sharedContract,
            (Join-Path $root 'tests\patient-interview\m07-other-medication-sql-contract.php'),
            (Join-Path $root 'tests\patient-interview\m06-conversational-flow-contract.php'),
            (Join-Path $root 'tests\patient-interview\m06-omission-contract.php'),
            (Join-Path $root 'tests\patient-interview\m04-m06-functional-contract.php')
        )) {
            try {
                $contractResult = Invoke-TextProcess 'post_dml_static_regression' $PhpPath @($contractPath)
                $staticResults[[IO.Path]::GetFileName($contractPath)] = if ($contractResult.ExitCode -eq 0) { 'PASS' } else { 'FAIL' }
            } catch { $staticResults[[IO.Path]::GetFileName($contractPath)] = 'FAIL' }
        }
        $result.STATIC_TESTS = if (@($staticResults.Values | Where-Object { $_ -ne 'PASS' }).Count -eq 0) { 'PASS' } else { 'FAIL' }
        $result.M06_REGRESSION = if ($staticResults['m06-conversational-flow-contract.php'] -eq 'PASS' -and $staticResults['m06-omission-contract.php'] -eq 'PASS' -and $staticResults['m04-m06-functional-contract.php'] -eq 'PASS') { 'PASS_STATIC' } else { 'FAIL' }
        $result.STATIC_TEST_DETAILS = $staticResults
    }
    if ($result.FAILURE_STAGE -eq 'NONE' -and $result.POST_RUN_INTEGRITY -eq 'PASS_EXPECTED_ONE_MEDICATION_ONE_AUDIT' -and $result.POST_RUN_SCHEMA_PREFLIGHT -eq 'PASS' -and $result.STATIC_TESTS -eq 'PASS') {
        $result.FINAL_STATUS = 'M07_MYSQL_INTEGRATION_ACCEPTED_SYNTHETIC'
    } else {
        $result.FINAL_STATUS = if ($result.FAILURE_STAGE -match 'precondition|schema_validation|baseline|snapshot') { 'BLOCKED' } else { 'PARTIAL_WITH_BLOCKERS' }
    }
    return [PSCustomObject]$result
}

function Assert-Focal {
    param([bool]$Condition, [string]$Name)
    $script:FocalAssertionCount++
    if (-not $Condition) { throw ('Focal assertion failed: ' + $Name) }
}

function Invoke-FocalProcessClassificationTest {
    $fixturePath = Join-Path ([IO.Path]::GetTempPath()) ('m07-powershell-51-focal-' + [Guid]::NewGuid().ToString('N') + '.php')
    $fixture = @'
<?php
$mode = isset($argv[1]) ? $argv[1] : '';
if ($mode === 'pass') { echo json_encode(array('status' => 'PASS')); exit(0); }
if ($mode === 'pass_stderr') { fwrite(STDERR, 'synthetic nonfatal diagnostic'); echo json_encode(array('status' => 'PASS')); exit(0); }
if ($mode === 'fail') { fwrite(STDERR, 'synthetic failure'); echo json_encode(array('status' => 'FAIL')); exit(3); }
if ($mode === 'blocked') { echo json_encode(array('status' => 'BLOCKED')); exit(2); }
if ($mode === 'not_reached') { echo json_encode(array('status' => 'NOT_REACHED')); exit(0); }
if ($mode === 'pdo_error') { echo json_encode(array('status' => 'FAIL', 'first_error_class' => 'PDOException', 'first_failed_operation' => 'synthetic_query', 'first_error_sqlstate' => '42S22', 'first_error_driver_code' => '1054', 'first_error_message_sanitized' => 'PDOException; SQLSTATE=42S22; DRIVER_CODE=1054')); exit(1); }
if ($mode === 'empty_error') { fwrite(STDERR, 'synthetic child failure'); exit(1); }
if ($mode === 'multi') { echo json_encode(array('status' => 'PASS')) . PHP_EOL; echo json_encode(array('status' => 'FAIL')); exit(0); }
if ($mode === 'invalid') { echo 'not-json'; exit(0); }
exit(7);
'@
    $script:FocalAssertionCount = 0
    try {
        Set-Content -LiteralPath $fixturePath -Value $fixture -Encoding ASCII

        $pass = Invoke-JsonPhp 'focal_pass' $fixturePath @('pass')
        Assert-Focal ($pass.ExitCode -eq 0 -and (Get-JsonStepStatus $pass @('PASS')) -eq 'PASS') 'pass_json_exit_zero'

        $passStderr = Invoke-JsonPhp 'focal_pass_stderr' $fixturePath @('pass_stderr')
        Assert-Focal ($passStderr.ExitCode -eq 0 -and [string]$passStderr.StdErr -match 'synthetic nonfatal diagnostic' -and (Get-JsonStepStatus $passStderr @('PASS')) -eq 'PASS') 'stderr_nonfatal_exit_zero'

        $textPassStderr = Invoke-TextProcess 'focal_text_pass_stderr' $PhpPath @($fixturePath, 'pass_stderr')
        Assert-Focal ($textPassStderr.ExitCode -eq 0 -and [string]$textPassStderr.StdErr -match 'synthetic nonfatal diagnostic') 'text_process_stderr_nonfatal'

        $fail = Invoke-JsonPhp 'focal_fail' $fixturePath @('fail')
        Assert-Focal ($fail.ExitCode -eq 3 -and (Get-JsonStepStatus $fail @('PASS')) -eq 'FAIL') 'nonzero_exit_rejected'

        $blocked = Invoke-JsonPhp 'focal_blocked' $fixturePath @('blocked')
        Assert-Focal ($blocked.ExitCode -eq 2 -and (Get-JsonStepStatus $blocked @('PASS')) -eq 'BLOCKED') 'blocked_status_preserved'

        $notReached = Invoke-JsonPhp 'focal_not_reached' $fixturePath @('not_reached')
        Assert-Focal ($notReached.ExitCode -eq 0 -and (Get-JsonStepStatus $notReached @('PASS')) -eq 'NOT_REACHED') 'not_reached_status_preserved'

        $pdoError = Invoke-JsonPhp 'focal_pdo_error' $fixturePath @('pdo_error')
        Assert-Focal ($pdoError.ExitCode -eq 1 -and (Get-JsonStepStatus $pdoError @('PASS')) -eq 'FAIL') 'sanitized_sqlstate_json_preserved'

        $invalidRejected = $false
        try { [void](Invoke-JsonPhp 'focal_invalid_json' $fixturePath @('invalid')) } catch { $invalidRejected = $true }
        Assert-Focal $invalidRejected 'invalid_json_rejected'

        $multiRejected = $false
        try { [void](Invoke-JsonPhp 'focal_multiple_json' $fixturePath @('multi')) } catch { $multiRejected = $true }
        Assert-Focal $multiRejected 'multiple_json_documents_rejected'

        $emptyErrorRejected = $false
        $emptyErrorMessage = ''
        try { [void](Invoke-JsonPhp 'focal_empty_error' $fixturePath @('empty_error')) } catch { $emptyErrorRejected = $true; $emptyErrorMessage = [string]$_.Exception.Message }
        Assert-Focal ($emptyErrorRejected -and $emptyErrorMessage -match 'SQLSTATE=NOT_VERIFIED') 'empty_stdout_does_not_infer_sql'

        $executionError = $false
        try { [void](Invoke-TextProcess 'focal_execution_error' (Join-Path ([IO.Path]::GetTempPath()) 'missing-m07-child.exe') @()) } catch { $executionError = $true }
        Assert-Focal $executionError 'process_start_error_rejected'

        [ordered]@{
            FOCAL_PROCESS_CLASSIFICATION = 'PASS'
            PS51_NATIVE_PROCESS_TESTS = 'PASS'
            JSON_STATUS_CLASSIFICATION_TESTS = 'PASS'
            NONZERO_EXIT_REJECTION = 'PASS'
            INVALID_JSON_REJECTION = 'PASS'
            MULTIPLE_JSON_REJECTION = 'PASS'
            EMPTY_STDOUT_REJECTION = 'PASS'
            SANITIZED_SQLSTATE_JSON = 'PASS'
            STDERR_NONFATAL_EXIT_ZERO = 'PASS'
            ASSERTIONS = $script:FocalAssertionCount
        } | ConvertTo-Json -Compress
    } finally {
        Remove-Item -LiteralPath $fixturePath -Force -ErrorAction SilentlyContinue
    }
}

if ($FocalProcessTest) {
    try {
        Invoke-FocalProcessClassificationTest
        exit 0
    } catch {
        Write-Output ([ordered]@{ FOCAL_PROCESS_CLASSIFICATION = 'FAIL'; ERROR = 'Prueba focal no superada.' } | ConvertTo-Json -Compress)
        exit 1
    }
}

if ($PendingDmlOnly) {
    $pendingResult = Invoke-PendingDmlOnlyRun
    Write-Output ($pendingResult | ConvertTo-Json -Depth 10)
    if ([string]$pendingResult.FINAL_STATUS -eq 'M07_MYSQL_INTEGRATION_ACCEPTED_SYNTHETIC') { exit 0 }
    exit 2
}

try {
    $currentCheckpoint = 'runtime_prerequisites'
    if (-not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) { throw 'No se encontró el ejecutable PHP indicado.' }
    foreach ($required in @($validator, $fixtureHelper, $migrationScript, $sharedContract, $canonicalIntegration, $legacyIntegration)) {
        if (-not (Test-Path -LiteralPath $required -PathType Leaf)) { throw 'Falta un arnés requerido.' }
    }
    $integrity = (& whoami.exe /groups /fo csv /nh 2>$null | Out-String)
    if ($integrity -notmatch '(?i)Medium Mandatory Level') { throw 'El ejecutor debe iniciarse desde PowerShell normal con integridad Medium.' }
    $lastCompletedCheckpoint = 'runtime_prerequisites'

    $currentCheckpoint = 'read_only_schema_validation'
    $schema = Invoke-JsonPhp 'read_only_schema_validation' $validator @("--database=$database", "--patient-a=$patientA", "--patient-b=$patientB")
    $schemaStatus = Get-JsonStepStatus $schema @('PASS_READ_ONLY_EXISTING_SCHEMA')
    if ($schema.ExitCode -ne 0 -or [string]$schema.Json.status -ne 'PASS_READ_ONLY_EXISTING_SCHEMA') {
        Register-Failure 'read_only_schema_validation' 'El esquema/backfill no está listo o no pudo verificarse.' $schema.ExitCode
        throw 'El esquema/backfill no está listo para validación existente.'
    }
    $lastCompletedCheckpoint = 'read_only_schema_validation'

    $currentCheckpoint = 'm07_shared_static_contract_invocation'
    $sharedContractResult = Invoke-TextProcess 'm07_shared_static_contract' $PhpPath @($sharedContract)
    $lastCompletedCheckpoint = 'm07_shared_static_contract_invocation'
    $currentCheckpoint = 'm07_shared_static_contract_status_classification'
    $sharedContractStatus = Get-TextStepStatus $sharedContractResult
    if ($sharedContractResult.ExitCode -ne 0) { throw 'Contrato estático canónico M07 falló.' }
    $lastCompletedCheckpoint = 'm07_shared_static_contract_status_classification'

    if (-not $AllowSyntheticWrites) {
        $readOnlyOnly = $true
    } else {
        $safety = Invoke-JsonPhp 'p04_fixture_preflight' $fixtureHelper @('--action=preflight', "--root=$root", "--database=$database", "--snapshot=$snapshotPath")
        $fixturePreflightStatus = Get-P04StepStatus $safety
        if ($safety.ExitCode -ne 0 -or -not $safety.Json.ok -or [string]$safety.Json.database_effective -ne $database) { Register-Failure 'p04_fixture_preflight' 'El preflight del fixture P04 falló.' $safety.ExitCode; throw 'P04 preflight falló.' }
        $prepare = Invoke-JsonPhp 'p04_fixture_snapshot' $fixtureHelper @('--action=prepare', "--root=$root", "--database=$database", "--snapshot=$snapshotPath")
        $fixtureSnapshotStatus = if ($prepare.ExitCode -eq 0 -and [bool]$prepare.Json.ok) { 'PASS' } else { Get-JsonStepStatus $prepare @('PASS') }
        if ($prepare.ExitCode -ne 0 -or -not $prepare.Json.ok) { Register-Failure 'p04_fixture_snapshot' 'No se pudo crear el snapshot focal.' $prepare.ExitCode; throw 'Snapshot P04 falló.' }
        $snapshotCreated = $true
        $create = Invoke-JsonPhp 'p04_fixture_credentials' $fixtureHelper @('--action=create', "--root=$root", "--database=$database", "--snapshot=$snapshotPath")
        $fixtureCredentialStatus = if ($create.ExitCode -eq 0 -and [bool]$create.Json.ok -and [bool]$create.Json.independent_active_credentials) { 'PASS' } else { Get-JsonStepStatus $create @('PASS') }
        if ($create.ExitCode -ne 0 -or -not $create.Json.ok -or -not $create.Json.independent_active_credentials) { Register-Failure 'p04_fixture_credentials' 'No se pudieron preparar credenciales sintéticas independientes.' $create.ExitCode; throw 'Credenciales P04 fallaron.' }

        $p04 = Invoke-JsonPhp 'p04_guard' (Join-Path $root 'tests\p04\verify-isolation.php') @("--database=$database", "--patient-a=$patientA", "--patient-b=$patientB", '--isolation-mode=cross-clinic', '--json')
        $p04Status = Get-P04StepStatus $p04
        if ($p04.ExitCode -ne 0 -or -not $p04.Json.ok) { Register-Failure 'p04_guard' 'P04 no pasó.' $p04.ExitCode; throw 'P04 falló.' }
        $canonical = Invoke-JsonPhp 'canonical_service_mysql' $canonicalIntegration @('--allow-synthetic-write', "--database=$database", "--patient-a=$patientA", "--patient-b=$patientB")
        $canonicalStatus = Get-JsonStepStatus $canonical @('PASS_SYNTHETIC_CANONICAL_SERVICE')
        if ($canonical.ExitCode -ne 0 -or [string]$canonical.Json.status -ne 'PASS_SYNTHETIC_CANONICAL_SERVICE' -or [string]$canonical.Json.restoration -ne 'PASS_FOCAL') { Register-Failure 'canonical_service_mysql' 'La prueba canónica o su restauración focal falló.' $canonical.ExitCode; throw 'Prueba canónica falló.' }
        $legacy = Invoke-JsonPhp 'traditional_p09_mysql' $legacyIntegration @('--allow-synthetic-write', "--database=$database", "--patient-a=$patientA", "--patient-b=$patientB")
        $legacyStatus = Get-JsonStepStatus $legacy @('PASS_SYNTHETIC_MYSQL_FOCAL')
        if ($legacy.ExitCode -ne 0 -or [string]$legacy.Json.status -ne 'PASS_SYNTHETIC_MYSQL_FOCAL') { Register-Failure 'traditional_p09_mysql' 'La regresión MySQL P09/M07 falló.' $legacy.ExitCode; throw 'Regresión tradicional falló.' }
        $staticContract = Invoke-TextProcess 'm07_static_contract' $PhpPath @((Join-Path $root 'tests\patient-interview\m07-conversational-implementation-contract.php'))
        $staticStatus = Get-TextStepStatus $staticContract
        if ($staticContract.ExitCode -ne 0) { throw 'Contrato estático M07 falló.' }
        $p09Contract = Invoke-TextProcess 'p09_static_contract' 'node' @((Join-Path $root 'tests\p09\contract-check.mjs'))
        $p09Status = Get-TextStepStatus $p09Contract
        if ($p09Contract.ExitCode -ne 0) { throw 'Contrato estático P09 falló.' }
    }
} catch {
    if ($firstFailedStep -eq $null) {
        $lineNumber = 0
        if ($null -ne $_.InvocationInfo) { $lineNumber = [int]$_.InvocationInfo.ScriptLineNumber }
        $exception = $_.Exception
        Register-Failure -Step 'runner' -Message (Get-SafeExceptionMessage $exception) -ExitCode 2 -Substep $currentCheckpoint -ScriptPath $PSCommandPath -LineNumber $lineNumber -ErrorType $exception.GetType().Name
    }
} finally {
    if ($snapshotCreated) {
        try { $restoration = Invoke-JsonPhp 'p04_fixture_restore' $fixtureHelper @('--action=restore', "--root=$root", "--database=$database", "--snapshot=$snapshotPath") }
        catch { Register-Failure 'p04_fixture_restore' 'La restauración focal no pudo verificarse.' 2 }
    }
}

$restored = $null -ne $restoration -and $restoration.ExitCode -eq 0 -and [bool]$restoration.Json.ok -and [bool]$restoration.Json.restoration_hash_verified
$fixtureRestorationStatus = if (-not $snapshotCreated) { 'NOT_REACHED' } elseif ($restored) { 'PASS' } else { 'FAIL' }
$canonicalRestorationStatus = Get-RestorationStatus $canonical 'restoration'
$legacyRestorationStatus = Get-RestorationStatus $legacy 'restore'
$clinicalRestorationStatus = if ($canonicalRestorationStatus -eq 'PASS' -and $legacyRestorationStatus -eq 'PASS') {
    'PASS'
} elseif ($canonicalRestorationStatus -eq 'BLOCKED' -or $legacyRestorationStatus -eq 'BLOCKED') {
    'BLOCKED'
} elseif ($canonicalRestorationStatus -eq 'FAIL' -or $legacyRestorationStatus -eq 'FAIL') {
    'FAIL'
} else {
    'NOT_REACHED'
}
$pdoStatus = if ($canonicalStatus -eq 'PASS' -and $legacyStatus -eq 'PASS') { 'PASS' } elseif ($canonicalStatus -eq 'BLOCKED' -or $legacyStatus -eq 'BLOCKED') { 'BLOCKED' } elseif ($canonicalStatus -eq 'NOT_REACHED' -and $legacyStatus -eq 'NOT_REACHED') { 'NOT_REACHED' } else { 'FAIL' }
$clinicalSnapshotStatus = if ($canonicalRestorationStatus -eq 'PASS' -or $legacyRestorationStatus -eq 'PASS') {
    'PASS_IN_MEMORY_FOCAL_SNAPSHOTS'
} elseif ($canonicalRestorationStatus -eq 'BLOCKED' -or $legacyRestorationStatus -eq 'BLOCKED') {
    'BLOCKED'
} else {
    'NOT_REACHED'
}
if ($restored -and (Test-Path -LiteralPath $snapshotPath)) { Remove-Item -LiteralPath $snapshotPath -Force -ErrorAction SilentlyContinue }
$final = if ($readOnlyOnly -and $firstFailedStep -eq $null) { 'PASS_READ_ONLY_EXISTING_SCHEMA' } elseif ($firstFailedStep -eq $null -and $restored) { 'PASS_VALIDATE_EXISTING' } elseif ($firstFailedStep -eq 'read_only_schema_validation') { 'BLOCKED_SCHEMA_NOT_READY' } elseif (-not $restored -and $snapshotCreated) { 'FAIL_REQUIRES_RECOVERY' } else { 'PARTIAL_WITH_BLOCKERS' }
[ordered]@{
    PHASE = 'M07_POST_MIGRATION_DIAGNOSIS'
    MYSQL_CONNECTION = if ($null -ne $schema -and $schema.ExitCode -eq 0) { 'PASS_LOCAL_PDO' } else { 'NOT_VERIFIED' }
    SYNTHETIC_DATABASE_VERIFIED = if ($null -ne $schema -and [string]$schema.Json.database_effective -eq 'PASS') { 'PASS' } else { 'NOT_VERIFIED' }
    READ_ONLY_SCHEMA_STATUS = $schemaStatus
    SCHEMA_INTEGRITY = if ($null -ne $schema) { [string]$schema.Json.schema_integrity } else { 'NOT_VERIFIED' }
    BACKFILL_CURRENT_STATE = if ($null -ne $schema) { [string]$schema.Json.backfill_current_state } else { 'NOT_VERIFIED' }
    HISTORICAL_DATA_INTEGRITY = if ($null -ne $schema) { [string]$schema.Json.historical_data_integrity } else { 'NOT_VERIFIED' }
    MIGRATION_SNAPSHOT_AVAILABLE = 'NOT_PROVIDED_TO_VALIDATE_EXISTING'
    LAST_COMPLETED_CHECKPOINT = $lastCompletedCheckpoint
    FIRST_FAILED_STEP = if ($null -ne $firstFailedStep) { $firstFailedStep } else { 'NONE' }
    FIRST_FAILED_SUBSTEP = if ($null -ne $firstFailedSubstep) { $firstFailedSubstep } else { 'NONE' }
    FIRST_FAILED_SCRIPT = if ($null -ne $firstFailedScript) { $firstFailedScript } else { 'NONE' }
    FIRST_FAILED_LINE = if ($null -ne $firstFailedLine) { $firstFailedLine } else { 0 }
    FIRST_ERROR_TYPE = if ($null -ne $firstErrorType) { $firstErrorType } else { 'NONE' }
    FIRST_ERROR_ORIGINAL_CLASS = if ($null -ne $firstErrorOriginalClass -and $firstErrorOriginalClass -ne '') { $firstErrorOriginalClass } else { 'NONE' }
    FIRST_ERROR_SQLSTATE = if ($null -ne $firstErrorSqlState -and $firstErrorSqlState -ne '') { $firstErrorSqlState } else { 'NONE' }
    FIRST_ERROR_DRIVER_CODE = if ($null -ne $firstErrorDriverCode -and $firstErrorDriverCode -ne '') { $firstErrorDriverCode } else { 'NONE' }
    FIRST_ERROR_TABLE = if ($null -ne $firstErrorTable -and $firstErrorTable -ne '') { $firstErrorTable } else { 'NONE' }
    FIRST_ERROR_CONSTRAINT = if ($null -ne $firstErrorConstraint -and $firstErrorConstraint -ne '') { $firstErrorConstraint } else { 'NONE' }
    FIRST_ERROR_MESSAGE_SANITIZED = if ($null -ne $firstError) { $firstError } else { 'NONE' }
    FIRST_ERROR_EXIT_CODE = if ($null -ne $firstExitCode) { $firstExitCode } else { 0 }
    P04_GUARD = $p04Status
    P04_FIXTURE_PREFLIGHT = $fixturePreflightStatus
    P04_FIXTURE_SNAPSHOT = $fixtureSnapshotStatus
    P04_CREDENTIAL_PREPARATION = $fixtureCredentialStatus
    CANONICAL_SERVICE_TESTS = $canonicalStatus
    TRADITIONAL_P09_TESTS = $legacyStatus
    M07_SHARED_STATIC_REGRESSION = $sharedContractStatus
    M07_CONVERSATIONAL_STATIC_REGRESSION = $staticStatus
    P09_STATIC_REGRESSION = $p09Status
    PDO_REGRESSION = $pdoStatus
    CLINICAL_SNAPSHOT = $clinicalSnapshotStatus
    CANONICAL_CLINICAL_RESTORATION = $canonicalRestorationStatus
    TRADITIONAL_CLINICAL_RESTORATION = $legacyRestorationStatus
    CLINICAL_FIXTURE_RESTORATION = $clinicalRestorationStatus
    VALIDATE_EXISTING_RUNNER = 'PREPARED_NO_DDL_NO_BACKFILL'
    DDL_EXECUTED = 'NO'
    BACKFILL_EXECUTED = 'NO'
    DATABASE_WRITES_BY_CODEX = 'NO'
    FIXTURE_RESTORATION = $fixtureRestorationStatus
    FIXTURE_RESTORATION_HASH = if ($restored) { 'PASS' } elseif ($snapshotCreated) { 'FAIL' } else { 'NOT_REACHED' }
    M07_CANONICAL_ACTIVATION_GATE = 'CLOSED_BY_DEFAULT_STATIC_GUARD'
    M07_FINALIZATION_ENABLED = 'NO'
    FINAL_STATUS = $final
    MANUAL_COMMAND = if ($readOnlyOnly) { '& .\tests\patient-interview\run-m07-validate-existing-medium.ps1 -Mode ValidateExisting' } else { '& .\tests\patient-interview\run-m07-validate-existing-medium.ps1 -Mode ValidateExisting -AllowSyntheticWrites' }
} | ConvertTo-Json -Depth 6

if ($final -ne 'PASS_VALIDATE_EXISTING') { exit 2 }
