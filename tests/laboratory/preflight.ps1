[CmdletBinding()]
param(
    [string]$ProjectRoot = '',
    [string]$TestDatabase = '',
    [string]$TestStorage = '',
    [switch]$Json
)

$ErrorActionPreference = 'Stop'

$checks = [ordered]@{}
$blocked = New-Object System.Collections.Generic.List[string]

function Add-Blocked([string]$Reason) {
    if (-not [string]::IsNullOrWhiteSpace($Reason) -and -not $blocked.Contains($Reason)) {
        [void]$blocked.Add($Reason)
    }
}

function Set-Check([string]$Name, [string]$Value) {
    $checks[$Name] = $Value
}

function Test-PathUnder([string]$Child, [string]$Parent) {
    $childFull = [System.IO.Path]::GetFullPath($Child).TrimEnd('\') + '\'
    $parentFull = [System.IO.Path]::GetFullPath($Parent).TrimEnd('\') + '\'
    return $childFull.StartsWith($parentFull, [System.StringComparison]::OrdinalIgnoreCase)
}

function Read-EnvValue([string]$Path, [string]$Key) {
    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        return ''
    }

    foreach ($line in @(Get-Content -LiteralPath $Path)) {
        if ($line -match ('^\s*' + [regex]::Escape($Key) + '\s*=\s*(.*)$')) {
            return $matches[1].Trim().Trim('"').Trim("'")
        }
    }
    return ''
}

try {
    if ([string]::IsNullOrWhiteSpace($ProjectRoot)) {
        $scriptDirectory = Split-Path -Parent $MyInvocation.MyCommand.Path
        $ProjectRoot = Join-Path $scriptDirectory '..\..'
    }
    $project = (Resolve-Path -LiteralPath $ProjectRoot -ErrorAction Stop).Path
    $envPath = Join-Path $project '.env'
    $currentDatabase = Read-EnvValue $envPath 'DB_DATABASE'
    $currentHost = Read-EnvValue $envPath 'DB_HOST'
    $currentAppEnv = Read-EnvValue $envPath 'APP_ENV'

    $checks['PROJECT_ROOT'] = 'PASS'
    $checks['CURRENT_DATABASE'] = if ($currentDatabase -eq 'bd_clinica') { 'BLOCKED_BD_CLINICA' } else { 'READ_ONLY' }
    $checks['CURRENT_DB_HOST'] = if ($currentHost -match '^(localhost|127\.0\.0\.1|::1)$') { 'PASS_LOCAL' } else { 'BLOCKED_NON_LOCAL' }
    $checks['CURRENT_APP_ENV'] = if ($currentAppEnv -match '^(prod|production)$') { 'BLOCKED_PRODUCTION' } else { 'READ_ONLY' }

    if ($currentDatabase -eq 'bd_clinica') {
        Add-Blocked 'La configuración principal apunta a bd_clinica; no se permite runtime mutante.'
    }
    if ($checks['CURRENT_DB_HOST'] -eq 'BLOCKED_NON_LOCAL') {
        Add-Blocked 'El host de base no es local; no se permite continuar.'
    }
    if ($checks['CURRENT_APP_ENV'] -eq 'BLOCKED_PRODUCTION') {
        Add-Blocked 'El entorno está marcado como producción; no se permite continuar.'
    }

    $allowedDatabases = @(
        'clinica_dolor_demo_20261010_final',
        'clinica_dolor_browser_20261012'
    )
    $checks['TEST_DATABASE_EXPLICIT'] = if ([string]::IsNullOrWhiteSpace($TestDatabase)) { 'BLOCKED_MISSING' } else { 'PASS' }
    $checks['TEST_DATABASE_ALLOWLIST'] = if ($allowedDatabases -contains $TestDatabase) { 'PASS' } else { 'BLOCKED_NOT_ALLOWLISTED' }
    if ([string]::IsNullOrWhiteSpace($TestDatabase)) {
        Add-Blocked 'Debe indicarse -TestDatabase explícitamente.'
    } elseif ($TestDatabase -eq 'bd_clinica') {
        Add-Blocked 'La base de pruebas no puede ser bd_clinica.'
    } elseif ($allowedDatabases -notcontains $TestDatabase) {
        Add-Blocked 'La base de pruebas no pertenece a la allowlist sintética conocida.'
    }

    $storageResolved = $null
    if ([string]::IsNullOrWhiteSpace($TestStorage)) {
        Set-Check 'TEST_STORAGE_EXPLICIT' 'BLOCKED_MISSING'
        Add-Blocked 'Debe indicarse -TestStorage explícitamente.'
    } elseif ($TestStorage -notmatch '^(?:[A-Za-z]:[\\/]|\\\\)') {
        Set-Check 'TEST_STORAGE_EXPLICIT' 'BLOCKED_NOT_ABSOLUTE'
        Add-Blocked 'El almacenamiento de pruebas debe ser una ruta absoluta.'
    } elseif (-not (Test-Path -LiteralPath $TestStorage -PathType Container)) {
        Set-Check 'TEST_STORAGE_EXPLICIT' 'BLOCKED_NOT_FOUND'
        Add-Blocked 'El almacenamiento de pruebas no existe; el preflight no lo crea.'
    } else {
        $storageResolved = (Resolve-Path -LiteralPath $TestStorage -ErrorAction Stop).Path
        Set-Check 'TEST_STORAGE_EXPLICIT' 'PASS'
        $storageItem = Get-Item -LiteralPath $storageResolved -ErrorAction Stop
        $storageInProject = Test-PathUnder $storageResolved $project
        $storageInProduction = $storageResolved -match '(?i)\\(?:bd_clinica|production|clinical|public\\storage)(?:\\|$)'
        $storageReparse = ($storageItem.Attributes -band [System.IO.FileAttributes]::ReparsePoint) -ne 0
        $checks['TEST_STORAGE_OUTSIDE_PROJECT'] = if ($storageInProject) { 'BLOCKED_INSIDE_PROJECT' } else { 'PASS' }
        $checks['TEST_STORAGE_NOT_CLINICAL'] = if ($storageInProduction) { 'BLOCKED_CLINICAL_PATH' } else { 'PASS' }
        $checks['TEST_STORAGE_NOT_REPARSE'] = if ($storageReparse) { 'BLOCKED_REPARSE_POINT' } else { 'PASS' }
        if ($storageInProject) { Add-Blocked 'El almacenamiento de pruebas está dentro del proyecto.' }
        if ($storageInProduction) { Add-Blocked 'El almacenamiento de pruebas parece clínico o de producción.' }
        if ($storageReparse) { Add-Blocked 'El almacenamiento de pruebas es un punto de reanálisis.' }
    }

    $checks['MYSQL_CONNECTION'] = 'NOT_EXECUTED_READ_ONLY_PREFLIGHT'
    $checks['HTTP_REQUESTS'] = 'NONE'
    $checks['DATABASE_QUERIES'] = 'NONE'
    $checks['MUTATIONS'] = 'NONE'
    $checks['APPLICATION_ENV_MODIFIED'] = 'NO'
    $checks['PHP_APACHE_EFFECTIVE_CONFIG'] = 'NOT_EXECUTED'

    $xamppIni = 'C:\xampp\php\php.ini'
    if (Test-Path -LiteralPath $xamppIni -PathType Leaf) {
        $uploadLimit = Read-EnvValue $xamppIni 'upload_max_filesize'
        $postLimit = Read-EnvValue $xamppIni 'post_max_size'
        $checks['XAMPP_PHP_INI_PRESENT'] = 'PASS_STATIC'
        $checks['XAMPP_UPLOAD_MAX_FILESIZE'] = if ($uploadLimit) { 'PRESENT' } else { 'NOT_FOUND' }
        $checks['XAMPP_POST_MAX_SIZE'] = if ($postLimit) { 'PRESENT' } else { 'NOT_FOUND' }
    } else {
        $checks['XAMPP_PHP_INI_PRESENT'] = 'NOT_FOUND'
        Add-Blocked 'No se encontró C:\xampp\php\php.ini para revisión estática.'
    }

    $status = if ($blocked.Count -eq 0) { 'PASS_READ_ONLY_GATE' } else { 'BLOCKED' }
    $result = [ordered]@{
        status = $status
        checks = $checks
        blocked = @($blocked)
        runtime_mutating_tests_executed = $false
        database_created = $false
        bd_clinica_modified = $false
        application_environment_modified = $false
    }

    if ($Json) {
        $result | ConvertTo-Json -Depth 5
    } else {
        Write-Output ('STATUS: ' + $status)
        foreach ($entry in $checks.GetEnumerator()) {
            Write-Output ($entry.Key + ': ' + $entry.Value)
        }
        if ($blocked.Count -gt 0) {
            Write-Output 'BLOCKERS:'
            foreach ($reason in $blocked) {
                Write-Output ('- ' + $reason)
            }
        }
    }

    if ($blocked.Count -gt 0) {
        exit 2
    }
    exit 0
} catch {
    Write-Output 'STATUS: BLOCKED'
    Write-Output 'BLOCKERS:'
    Write-Output '- Error de preflight; no se ejecutaron operaciones mutantes.'
    exit 2
}
