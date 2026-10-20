[CmdletBinding()]
param(
    [string]$PhpPath = 'C:\xampp\php\php.exe',
    [string]$Database = 'clinica_dolor_demo_20261010_final',
    [int]$PatientA = 9301,
    [int]$PatientB = 9302,
    [string]$BaselinePath = 'C:\Users\silvi\AppData\Local\ClinicaDolor\M07-baselines\m07-clinical-baseline-20261015T152934Z-7701ca5e.json',
    [int]$TimeoutSeconds = 90,
    [switch]$ProbeCliArguments
)

$ErrorActionPreference = 'Stop'

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$approvedBaseline = 'C:\Users\silvi\AppData\Local\ClinicaDolor\M07-baselines\m07-clinical-baseline-20261015T152934Z-7701ca5e.json'
$stage = 'initialization'
$runtime = $null
$lastChildDiagnostic = $null

function Fail-Run([string]$Message) {
    $payload = [ordered]@{
        phase = 'M07_B1_READ_ONLY_MEDIUM_RUNNER'
        status = 'BLOCKED_READ_ONLY_RUN'
        failure_stage = $script:stage
        error_message_sanitized = $Message
        database = $script:Database
        patients = '9301_9302_ONLY'
        mysql_writes = 'NO'
        http_executed = 'NO'
    }
    if ($null -ne $script:lastChildDiagnostic) {
        $payload.child_process = $script:lastChildDiagnostic.child_process
        $payload.child_exit_code = $script:lastChildDiagnostic.child_exit_code
        $payload.child_stdout_length = $script:lastChildDiagnostic.child_stdout_length
        $payload.child_stderr_length = $script:lastChildDiagnostic.child_stderr_length
        $payload.child_json_valid = $script:lastChildDiagnostic.child_json_valid
        $payload.child_status = $script:lastChildDiagnostic.child_status
        $payload.first_failed_condition = $script:lastChildDiagnostic.first_failed_condition
        $payload.first_error_type = $script:lastChildDiagnostic.first_error_type
        $payload.first_error_message_sanitized = $script:lastChildDiagnostic.first_error_message_sanitized
        $payload.parent_requested_database = $script:lastChildDiagnostic.parent_requested_database
        $payload.child_requested_database = $script:lastChildDiagnostic.child_requested_database
        $payload.database_argument_match = $script:lastChildDiagnostic.database_argument_match
        $payload.root_cause_classification = $script:lastChildDiagnostic.root_cause_classification
        $payload.root_cause_evidence = $script:lastChildDiagnostic.root_cause_evidence
        $payload.database_connection_reached = $script:lastChildDiagnostic.database_connection_reached
        $payload.effective_database_confirmed = $script:lastChildDiagnostic.effective_database_confirmed
        $payload.schema_checks_reached = $script:lastChildDiagnostic.schema_checks_reached
        $payload.stderr_category = $script:lastChildDiagnostic.stderr_category
    }
    $payload | ConvertTo-Json -Depth 5
    exit 2
}

function Get-ChildRootCauseClass(
    [string]$Step,
    [int]$ExitCode,
    [bool]$JsonValid
) {
    switch -Regex ($Step) {
        '^mysql_connection$' { return 'DATABASE_CONNECTION_FAILURE' }
        '^database_identity$' { return 'SYNTHETIC_DATABASE_NOT_READY' }
        '^(schema|schema_or_historical_integrity)' { return 'SCHEMA_CONTRACT_MISMATCH' }
        '^(synthetic_patient|synthetic_clinic|scoped_.*integrity)' { return 'SYNTHETIC_DATABASE_NOT_READY' }
        '^(allowlist|patient_allowlist|local_configuration|local_connection_guard)$' { return 'HARNESS_DEFECT' }
    }
    if ($ExitCode -ne 0 -and -not $JsonValid) {
        return 'UNDETERMINED_REQUIRES_RUNTIME_EVIDENCE'
    }
    return 'UNDETERMINED_REQUIRES_RUNTIME_EVIDENCE'
}

function Quote-ProcessArgument([string]$Argument) {
    if ([string]::IsNullOrEmpty($Argument)) {
        return '""'
    }
    if ($Argument -notmatch '[\s"]') {
        return $Argument
    }

    $builder = New-Object System.Text.StringBuilder
    [void]$builder.Append('"')
    $slashes = 0
    for ($index = 0; $index -lt $Argument.Length; $index++) {
        $character = $Argument[$index]
        if ($character -eq '\') {
            $slashes++
            continue
        }
        if ($character -eq '"') {
            for ($backslash = 0; $backslash -lt (2 * $slashes + 1); $backslash++) {
                [void]$builder.Append('\')
            }
            [void]$builder.Append('"')
            $slashes = 0
            continue
        }
        for ($backslash = 0; $backslash -lt $slashes; $backslash++) {
            [void]$builder.Append('\')
        }
        [void]$builder.Append($character)
        $slashes = 0
    }
    for ($backslash = 0; $backslash -lt (2 * $slashes); $backslash++) {
        [void]$builder.Append('\')
    }
    [void]$builder.Append('"')
    return $builder.ToString()
}

function Get-ChildDiagnostic(
    [string]$Name,
    [int]$ExitCode,
    [string]$Stdout,
    [string]$Stderr,
    [string]$ExpectedDatabase
) {
    $json = $null
    $jsonValid = $false
    if (-not [string]::IsNullOrWhiteSpace($Stdout)) {
        try {
            $json = $Stdout | ConvertFrom-Json
            $jsonValid = ($null -ne $json)
        } catch {
            $json = $null
            $jsonValid = $false
        }
    }

    $status = if ($jsonValid) { [string]$json.status } else { 'NOT_AVAILABLE' }
    $step = if ($jsonValid) { [string]$json.first_failed_step } else { 'NOT_AVAILABLE' }
    $message = if ($jsonValid) { [string]$json.error_message_sanitized } else { 'No existe un resultado JSON controlado.' }
    $requestedDatabase = if ($jsonValid) { [string]$json.database } else { 'NOT_AVAILABLE' }
    $databaseEffective = if ($jsonValid) { [string]$json.database_effective } else { 'NOT_VERIFIED' }
    $schemaReached = 'NOT_VERIFIED'
    if ($status -eq 'PASS_READ_ONLY_EXISTING_SCHEMA') {
        $schemaReached = 'PASS'
    } elseif ($step -match 'schema|backfill|historical|clinical_state') {
        $schemaReached = 'YES'
    } elseif ($step -in @('mysql_connection', 'database_identity', 'local_configuration', 'local_connection_guard')) {
        $schemaReached = 'NO'
    }

    $stderrCategory = 'NONE'
    if (-not [string]::IsNullOrWhiteSpace($Stderr)) {
        if ($Stderr -match '(?i)warning|notice|deprecated') {
            $stderrCategory = 'PHP_NONFATAL_DIAGNOSTIC'
        } elseif ($Stderr -match '(?i)fatal|uncaught|exception|error') {
            $stderrCategory = 'PHP_RUNTIME_OR_EXCEPTION'
        } else {
            $stderrCategory = 'UNCLASSIFIED_STDERR'
        }
    }

    $evidence = if ($jsonValid) {
        'JSON_VALID; STATUS=' + $status + '; FIRST_FAILED_STEP=' + $step + '; DATABASE_EFFECTIVE=' + $databaseEffective
    } elseif (-not [string]::IsNullOrWhiteSpace($Stderr)) {
        'NO_VALID_JSON; STDERR_PRESENT; CONTENT_NOT_EMITTED'
    } else {
        'NO_VALID_JSON; STDERR_EMPTY'
    }

    return [pscustomobject]@{
        child_process = $Name
        child_exit_code = $ExitCode
        child_stdout_length = if ($null -eq $Stdout) { 0 } else { $Stdout.Length }
        child_stderr_length = if ($null -eq $Stderr) { 0 } else { $Stderr.Length }
        child_json_valid = $jsonValid
        child_status = $status
        first_failed_condition = $step
        first_error_type = if ($jsonValid) { 'CONTROLLED_CHILD_RESULT' } else { 'CHILD_JSON_UNAVAILABLE' }
        first_error_message_sanitized = $message
        parent_requested_database = $ExpectedDatabase
        child_requested_database = $requestedDatabase
        database_argument_match = if ($jsonValid -and $requestedDatabase -eq $ExpectedDatabase) { 'PASS' } elseif ($jsonValid) { 'FAIL' } else { 'NOT_VERIFIED' }
        root_cause_classification = Get-ChildRootCauseClass $step $ExitCode $jsonValid
        root_cause_evidence = $evidence
        database_connection_reached = if ($jsonValid -and $databaseEffective -eq 'PASS') { 'PASS' } elseif ($jsonValid -and $step -eq 'mysql_connection') { 'NO' } else { 'NOT_VERIFIED' }
        effective_database_confirmed = if ($databaseEffective -eq 'PASS') { 'PASS' } else { 'NO' }
        schema_checks_reached = $schemaReached
        stderr_category = $stderrCategory
    }
}

function Invoke-PhpJson(
    [string]$Php,
    [string]$Script,
    [string[]]$Arguments,
    [string]$Name,
    [int]$Timeout
) {
    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName = $Php
    $quoted = foreach ($argument in $Arguments) {
        Quote-ProcessArgument $argument
    }
    $scriptArgument = Quote-ProcessArgument $Script
    $psi.Arguments = (($scriptArgument + ' ' + ($quoted -join ' ')).Trim())
    $psi.WorkingDirectory = $projectRoot
    $psi.UseShellExecute = $false
    $psi.CreateNoWindow = $true
    $psi.RedirectStandardOutput = $true
    $psi.RedirectStandardError = $true
    $process = New-Object System.Diagnostics.Process
    $process.StartInfo = $psi
    try {
        if (-not $process.Start()) {
            throw 'No fue posible iniciar PHP.'
        }
        $stdoutTask = $process.StandardOutput.ReadToEndAsync()
        $stderrTask = $process.StandardError.ReadToEndAsync()
        if (-not $process.WaitForExit($Timeout * 1000)) {
            try { $process.Kill() } catch { }
            throw 'El proceso PHP excedió el tiempo máximo.'
        }
        $process.WaitForExit()
        $stdout = $stdoutTask.Result
        $stderr = $stderrTask.Result
        $exitCode = $process.ExitCode
        $diagnostic = Get-ChildDiagnostic $Name $exitCode $stdout $stderr $script:Database
        $script:lastChildDiagnostic = $diagnostic
        $parsedJson = $null
        if ($diagnostic.child_json_valid) {
            $parsedJson = $stdout | ConvertFrom-Json
        }
        return [pscustomobject]@{
            Name = $Name
            Result = $parsedJson
            ExitCode = $exitCode
            StdoutLength = if ($null -eq $stdout) { 0 } else { $stdout.Length }
            StderrLength = if ($null -eq $stderr) { 0 } else { $stderr.Length }
            CommandLine = $psi.Arguments
            Diagnostic = $diagnostic
        }
    } catch {
        if ($null -eq $script:lastChildDiagnostic) {
            $script:lastChildDiagnostic = [pscustomobject]@{
                child_process = $Name
                child_exit_code = 'NOT_AVAILABLE'
                child_json_valid = $false
                child_status = 'NOT_AVAILABLE'
                first_failed_condition = 'PROCESS_LAUNCH_OR_TIMEOUT'
                first_error_type = 'PHP_RUNTIME_FAILURE'
                first_error_message_sanitized = 'El proceso PHP no pudo terminar de forma verificable.'
                parent_requested_database = $script:Database
                child_requested_database = 'NOT_AVAILABLE'
                database_argument_match = 'NOT_VERIFIED'
                root_cause_classification = 'PHP_RUNTIME_FAILURE'
                root_cause_evidence = 'PROCESS_RESULT_UNAVAILABLE'
                database_connection_reached = 'NOT_VERIFIED'
                effective_database_confirmed = 'NO'
                schema_checks_reached = 'NOT_VERIFIED'
                stderr_category = 'NOT_AVAILABLE'
            }
        }
        throw
    } finally {
        $process.Dispose()
    }
}

if ($ProbeCliArguments) {
    $stage = 'cli_argument_probe'
    if (-not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) {
        Fail-Run 'No existe el ejecutable PHP indicado para la prueba CLI.'
    }
    $probePath = Join-Path $projectRoot 'tests\patient-interview\m07-b1-cli-argv-probe.php'
    $common = @(
        ('--database=' + [string]$Database)
        ('--patient-a=' + [string]$PatientA)
        ('--patient-b=' + [string]$PatientB)
    )
    $probe = Invoke-PhpJson $PhpPath $probePath ($common + @('--json=1')) 'cli-argv-probe-actual-path' $TimeoutSeconds
    $spaceProbe = Invoke-PhpJson $PhpPath $probePath ($common + @('--label=synthetic value')) 'cli-argv-probe-spaced-value' $TimeoutSeconds
    $probeFailed = $probe.ExitCode -ne 0 -or $null -eq $probe.Result -or $probe.Result.status -ne 'PASS_CLI_ARGUMENTS'
    $spaceProbeFailed = $spaceProbe.ExitCode -ne 0 -or $null -eq $spaceProbe.Result -or $spaceProbe.Result.status -ne 'PASS_CLI_ARGUMENTS'
    if ($probeFailed -or $spaceProbeFailed) {
        [ordered]@{
            phase = 'M07_B1_CLI_ARGUMENT_TRANSMISSION_PROBE'
            status = 'FAIL_CLI_ARGUMENTS'
            actual_runner_invocation_path_tested = $true
            runner_path = $PSCommandPath
            runner_sha256 = (Get-FileHash -LiteralPath $PSCommandPath -Algorithm SHA256).Hash
            probe_exit_code = $probe.ExitCode
            probe_json_valid = [bool]($null -ne $probe.Result)
            probe_command_line = $probe.CommandLine
            probe_stdout_length = $probe.StdoutLength
            probe_stderr_length = $probe.StderrLength
            spaced_probe_exit_code = $spaceProbe.ExitCode
            spaced_probe_json_valid = [bool]($null -ne $spaceProbe.Result)
            spaced_probe_command_line = $spaceProbe.CommandLine
            spaced_probe_stdout_length = $spaceProbe.StdoutLength
            spaced_probe_stderr_length = $spaceProbe.StderrLength
            mysql_executed = 'NO'
            http_executed = 'NO'
        } | ConvertTo-Json -Depth 5
        exit 1
    }
    $runnerHash = (Get-FileHash -LiteralPath $PSCommandPath -Algorithm SHA256).Hash
    [ordered]@{
        phase = 'M07_B1_CLI_ARGUMENT_TRANSMISSION_PROBE'
        status = 'PASS_CLI_ARGUMENTS'
        actual_runner_invocation_path_tested = $true
        runner_path = $PSCommandPath
        runner_sha256 = $runnerHash
        child_exit_code = $probe.ExitCode
        php_arguments_independent = [bool]$probe.Result.arguments_independent
        database_argument_exact = [bool]$probe.Result.database_argument_exact
        patient_a_argument_exact = [bool]$probe.Result.patient_a_argument_exact
        patient_b_argument_exact = [bool]$probe.Result.patient_b_argument_exact
        patient_arguments_exact = [bool]$probe.Result.patient_arguments_exact
        order_exact = [bool]$probe.Result.order_exact
        spaced_value_exact = [bool]$spaceProbe.Result.spaced_value_exact
        spaced_value_child_exit_code = $spaceProbe.ExitCode
        database_argument_match = 'PASS'
        mysql_executed = 'NO'
        http_executed = 'NO'
    } | ConvertTo-Json -Depth 5
    exit 0
}

try {
    $stage = 'argument_guards'
    if ($Database -ne 'clinica_dolor_demo_20261010_final' -or $PatientA -ne 9301 -or $PatientB -ne 9302) {
        Fail-Run 'La allowlist exige la base sintética y los pacientes 9301/9302.'
    }
    if ($BaselinePath -ne $approvedBaseline) {
        Fail-Run 'Solo se admite el baseline M07 aprobado; no se crea ni sobrescribe otro.'
    }
    if (-not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) {
        Fail-Run 'No existe el ejecutable PHP indicado.'
    }
    if (-not (Test-Path -LiteralPath $BaselinePath -PathType Leaf)) {
        Fail-Run 'No existe el baseline durable aprobado.'
    }

    $runtime = Join-Path ([IO.Path]::GetTempPath()) ('m07-b1-readonly-' + ([Guid]::NewGuid().ToString('N')))
    New-Item -ItemType Directory -Path $runtime -Force | Out-Null
    $php = (Resolve-Path -LiteralPath $PhpPath).Path
    $common = @(
        ('--database=' + [string]$Database)
        ('--patient-a=' + [string]$PatientA)
        ('--patient-b=' + [string]$PatientB)
    )

    $checks = @()
    $stage = 'existing_schema_read_only_validation'
    $checks += Invoke-PhpJson $php (Join-Path $projectRoot 'tests\patient-interview\m07-shared-canonical-validate-existing.php') ($common + @('--json=1')) 'schema' $TimeoutSeconds
    if ($checks[-1].ExitCode -ne 0) {
        Fail-Run 'La validación read-only del esquema terminó con fallo controlado.'
    }
    if ($checks[-1].Result.status -ne 'PASS_READ_ONLY_EXISTING_SCHEMA') {
        Fail-Run 'La validación read-only del esquema sintético no pasó.'
    }

    $stage = 'b1_shared_read_validation'
    $checks += Invoke-PhpJson $php (Join-Path $projectRoot 'tests\patient-interview\m07-b1-readonly-acceptance.php') $common 'b1' $TimeoutSeconds
    if ($checks[-1].ExitCode -ne 0) {
        Fail-Run 'La lectura funcional B1 terminó con fallo controlado.'
    }
    if ($checks[-1].Result.status -ne 'PASS_B1_READ_ONLY') {
        Fail-Run 'La lectura funcional B1 no pasó.'
    }

    $stage = 'approved_baseline_compare'
    $baselineArgs = @(
        $common
        '--mode=compare'
        ('--snapshot=' + [string]$BaselinePath)
    )
    $checks += Invoke-PhpJson $php (Join-Path $projectRoot 'tests\patient-interview\m07-durable-clinical-baseline.php') $baselineArgs 'baseline' $TimeoutSeconds
    if ($checks[-1].ExitCode -ne 0) {
        Fail-Run 'La comparación contra el baseline terminó con diferencia o fallo controlado.'
    }
    if ($checks[-1].Result.status -ne 'PASS_BASELINE_MATCH') {
        Fail-Run 'La comparación contra el baseline aprobado no pasó.'
    }

    $stage = 'report'
    [ordered]@{
        phase = 'M07_B1_READ_ONLY_MEDIUM_RUNNER'
        status = 'PASS_B1_READ_ONLY_PREPARED_RESULT'
        database = 'clinica_dolor_demo_20261010_final'
        patients = '9301_9302_ONLY'
        schema_validation = 'PASS_READ_ONLY_EXISTING_SCHEMA'
        shared_read = 'PASS_B1_READ_ONLY'
        durable_baseline = 'PASS_BASELINE_MATCH'
        temporary_credentials = 'NOT_REQUIRED'
        mysql_writes = 'NO'
        http_executed = 'NO'
    } | ConvertTo-Json -Depth 5
} catch {
    if ($_.Exception.Message -like 'El script read-only terminó con código 1.*') {
        Fail-Run 'Un script read-only reportó una diferencia o fallo funcional; revisar su JSON local sin ejecutar escrituras.'
    }
    if ($_.Exception.Message -like 'El proceso PHP excedió*') {
        Fail-Run 'El proceso read-only excedió el tiempo máximo.'
    }
    Fail-Run 'La ejecución read-only no pudo completarse; no se expone la salida del proceso.'
} finally {
    if ($null -ne $runtime -and (Test-Path -LiteralPath $runtime)) {
        Remove-Item -LiteralPath $runtime -Recurse -Force -ErrorAction SilentlyContinue
    }
}

