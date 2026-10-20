param(
    [int]$Port = 8117,
    [switch]$PreflightOnly,
    [switch]$ValidateOnly,
    [string]$ApacheExe = 'C:\xampp\apache\bin\httpd.exe'
)

$ErrorActionPreference = 'Stop'

if ($Port -ne 8117) {
    throw 'Fail-closed: el entorno M07 sintético sólo admite el puerto 8117.'
}
if ($PreflightOnly -and $ValidateOnly) {
    throw 'Las opciones -PreflightOnly y -ValidateOnly son mutuamente excluyentes.'
}

$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$apacheRoot = 'C:\xampp\apache'
$templatePath = Join-Path $PSScriptRoot 'apache-m07-synthetic.conf'
$envPath = Join-Path $root '.env'
$database = 'clinica_dolor_demo_20261010_final'

if (-not (Test-Path -LiteralPath $ApacheExe -PathType Leaf)) {
    throw 'No se encontró el ejecutable Apache permitido.'
}
if (-not (Test-Path -LiteralPath $templatePath -PathType Leaf)) {
    throw 'No se encontró la plantilla Apache sintética.'
}
if (-not (Test-Path -LiteralPath $envPath -PathType Leaf)) {
    throw 'No se encontró el archivo local de configuración.'
}

function Read-LocalEnvValue([string]$Name, [hashtable]$Values) {
    if (-not $Values.ContainsKey($Name)) {
        throw "Falta la variable local requerida: $Name"
    }
    return [string]$Values[$Name]
}

$localEnv = @{}
foreach ($line in Get-Content -LiteralPath $envPath) {
    $trimmed = $line.Trim()
    if ($trimmed -eq '' -or $trimmed.StartsWith('#') -or $trimmed.IndexOf('=') -lt 1) {
        continue
    }
    $parts = $trimmed.Split('=', 2)
    $key = $parts[0].Trim()
    $value = $parts[1].Trim()
    if ($value.Length -ge 2 -and (($value.StartsWith('"') -and $value.EndsWith('"')) -or ($value.StartsWith("'") -and $value.EndsWith("'")))) {
        $value = $value.Substring(1, $value.Length - 2)
    }
    $localEnv[$key] = $value
}

$dbUser = Read-LocalEnvValue 'DB_USERNAME' $localEnv
$dbPassword = Read-LocalEnvValue 'DB_PASSWORD' $localEnv

$runtimeBase = Join-Path ([IO.Path]::GetTempPath()) 'clinica-m07-http-isolated'
$runId = [Guid]::NewGuid().ToString('N')
$runtime = Join-Path $runtimeBase $runId
$sessions = Join-Path $runtime 'sessions'
$tmp = Join-Path $runtime 'tmp'
New-Item -ItemType Directory -Force -Path $sessions, $tmp | Out-Null

$resolvedConfig = Join-Path $runtime 'httpd.conf'
$prependPath = (Join-Path $PSScriptRoot 'web-database-preflight.php').Replace('\', '/')
$rootForApache = $root.Replace('\', '/')
$runtimeForApache = $runtime.Replace('\', '/')
$config = Get-Content -LiteralPath $templatePath -Raw
$config = $config.Replace('__M07_PORT__', [string]$Port)
$config = $config.Replace('__M07_ROOT__', $rootForApache)
$config = $config.Replace('__M07_RUNTIME__', $runtimeForApache)
$config = $config.Replace('__M07_PREPEND__', $prependPath)
Set-Content -LiteralPath $resolvedConfig -Value $config -Encoding UTF8

if ($ValidateOnly) {
    try {
        & $ApacheExe '-f' $resolvedConfig '-d' $apacheRoot '-t'
        if ($LASTEXITCODE -ne 0) {
            throw "La configuración Apache sintética no es válida. Código: $LASTEXITCODE"
        }
        Write-Output 'APACHE_CONFIG_STATIC_CHECK=PASS'
    } finally {
        if (Test-Path -LiteralPath $resolvedConfig) {
            Remove-Item -LiteralPath $resolvedConfig -Force
        }
        if (Test-Path -LiteralPath $runtime) {
            Remove-Item -LiteralPath $runtime -Recurse -Force
        }
    }
    exit 0
}

$listener = Get-NetTCPConnection -State Listen -LocalAddress '127.0.0.1' -LocalPort $Port -ErrorAction SilentlyContinue
if ($listener) {
    throw "Fail-closed: el puerto $Port ya está ocupado por otro proceso."
}

$appKey = [Guid]::NewGuid().ToString('N')
$tracePath = Join-Path $runtime 'launcher-trace.log'
$stdoutPath = Join-Path $runtime 'apache-stdout.log'
$stderrPath = Join-Path $runtime 'apache-stderr.log'

function Write-LauncherTrace([string]$Stage) {
    try {
        Add-Content -LiteralPath $tracePath -Value (([DateTime]::UtcNow.ToString('o')) + ' ' + $Stage)
    } catch {
        # La trazabilidad no debe bloquear ni cambiar el resultado del arranque.
    }
}

Write-LauncherTrace 'runtime_prepared'

# Start-Process recibe archivos para stdout/stderr. Esto evita que Apache o sus
# trabajadores hereden los pipes del PowerShell llamador y elimina cualquier
# espera de stream o de salida al terminar este iniciador.
$environmentValues = [ordered]@{
    APP_ENV = 'testing'
    APP_KEY = $appKey
    APP_NAME = 'ClinicaDolor-M07-Synthetic'
    DB_HOST = '127.0.0.1'
    DB_PORT = '3306'
    DB_DATABASE = $database
    DB_USERNAME = $dbUser
    DB_PASSWORD = $dbPassword
    CLINICAL_AI_SYNTHETIC_MODE = '1'
    CLINICAL_AI_EXTERNAL_ENABLED = '0'
    CLINICAL_AI_SUMMARY_ENABLED = '0'
    GEMINI_API_KEY = ''
    GEMINI_ENDPOINT = ''
    OPENAI_API_KEY = ''
    M07_HTTP_ISOLATED = '1'
    PREFLIGHT_ONLY = if ($PreflightOnly) { '1' } else { '0' }
}
$previousEnvironment = @{}
$process = $null
$processStarted = $false
$expectedApachePath = [IO.Path]::GetFullPath($ApacheExe)

function Stop-StartedApacheTree([int]$RootPid) {
    if ($RootPid -le 0) { return }
    $candidate = Get-Process -Id $RootPid -ErrorAction SilentlyContinue
    if ($null -eq $candidate) { return }
    try {
        $candidatePath = [IO.Path]::GetFullPath([string]$candidate.Path)
        if ($candidatePath -ne $expectedApachePath) { return }
        & taskkill.exe /PID $RootPid /T /F >$null 2>$null
        Write-LauncherTrace ('process_tree_stopped pid=' + [string]$RootPid)
    } catch {
        Write-LauncherTrace 'process_tree_identity_unverified'
    }
}

foreach ($name in $environmentValues.Keys) {
    $previousEnvironment[$name] = [Environment]::GetEnvironmentVariable($name, 'Process')
}

try {
    foreach ($name in $environmentValues.Keys) {
        [Environment]::SetEnvironmentVariable($name, [string]$environmentValues[$name], 'Process')
    }

    Write-LauncherTrace 'launch_begin'
    $argumentList = @('-f', ('"' + $resolvedConfig + '"'), '-d', ('"' + $apacheRoot + '"'))
    $process = Start-Process -FilePath $ApacheExe `
        -ArgumentList $argumentList `
        -WorkingDirectory $apacheRoot `
        -WindowStyle Hidden `
        -RedirectStandardOutput $stdoutPath `
        -RedirectStandardError $stderrPath `
        -PassThru
    $processStarted = $true
    Write-LauncherTrace ('launch_returned pid=' + [string]$process.Id)
} finally {
    foreach ($name in $environmentValues.Keys) {
        [Environment]::SetEnvironmentVariable($name, $previousEnvironment[$name], 'Process')
    }
}

if (-not $processStarted -or $null -eq $process) {
    throw 'No fue posible iniciar el proceso Apache sintético.'
}

$startupDeadline = [DateTime]::UtcNow.AddSeconds(10)
$listener = $null
try {
    while ([DateTime]::UtcNow -lt $startupDeadline) {
        Start-Sleep -Milliseconds 100
        if ($process.HasExited) {
            Write-LauncherTrace 'process_exited_before_listener'
            throw 'Apache sintético terminó antes de abrir su listener.'
        }
        $listener = Get-NetTCPConnection -State Listen -LocalAddress '127.0.0.1' -LocalPort $Port -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($listener) {
            Write-LauncherTrace ('listener_detected pid=' + [string]$listener.OwningProcess)
            if ([int]$listener.OwningProcess -ne $process.Id) {
                throw 'Fail-closed: el listener pertenece a un PID distinto.'
            }
            break
        }
    }

    if (-not $listener) {
        Write-LauncherTrace 'startup_timeout'
        throw 'Apache sintético no abrió el puerto reservado.'
    }

    Write-LauncherTrace 'listener_guard_passed'

    $state = [ordered]@{
        pid = [int]$process.Id
        port = $Port
        executable = $expectedApachePath
        config = $resolvedConfig
        runtime = $runtime
        startedUtc = [DateTime]::UtcNow.ToString('o')
        database = $database
        preflightOnly = [bool]$PreflightOnly
        controlPid = [int]$process.Id
        listenerPid = [int]$listener.OwningProcess
        stdoutRedirected = $true
        stderrRedirected = $true
        stdoutPath = $stdoutPath
        stderrPath = $stderrPath
        tracePath = $tracePath
    }
    $state | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $runtime 'process.json') -Encoding UTF8
    Write-LauncherTrace 'state_written'
} catch {
    Stop-StartedApacheTree -RootPid ([int]$process.Id)
    throw
}

Write-Output ('PID=' + $process.Id)
Write-Output ('PORT=' + $Port)
Write-Output ('DATABASE=' + $database)
Write-Output ('URL=http://127.0.0.1:' + $Port)
Write-Output ('RUNTIME=' + $runtime)
