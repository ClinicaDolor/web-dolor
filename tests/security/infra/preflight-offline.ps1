[CmdletBinding()]
param(
    [string]$Context = 'desktop-linux',
    [string]$ComposeFile = (Join-Path $PSScriptRoot 'compose.offline.yml'),
    [string]$FixtureManifest = (Join-Path $PSScriptRoot 'fixtures.sha256'),
    [string]$SecretDirectory = '',
    [string]$Image = 'mariadb:10.6'
)

$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path
$fixturesRoot = (Resolve-Path (Join-Path $repoRoot 'tests\fixtures')).Path
$projectName = 'th-sec-env-bootstrap-03r'
$containerName = 'th-sec-env-bootstrap-03r-db'
$networkName = 'th-sec-env-bootstrap-03r-internal'
$volumeName = 'th-sec-env-bootstrap-03r-db'
$allowedEndpoint = 'npipe:////./pipe/dockerDesktopLinuxEngine'
$approvedImage = 'mariadb:10.6'
$ownerReportedImageIdPrefix = '1e008264230a'
$allowedReferenceDatabase = 'clinica_dolor_demo_20261010_final'
$allowedDisposableDatabase = 'clinica_dolor_e2e_disposable_20261012'
$blockedDatabase = 'bd_clinica'

$checks = [ordered]@{}
$details = [ordered]@{}
$blocked = New-Object System.Collections.Generic.List[string]

function Add-Blocked([string]$Reason) {
    if (-not [string]::IsNullOrWhiteSpace($Reason) -and -not $blocked.Contains($Reason)) {
        [void]$blocked.Add($Reason)
    }
}

function Set-Check([string]$Name, [string]$Value) {
    $checks[$Name] = $Value
}

function Get-ErrorClass([string]$Text) {
    if ($Text -match '(?i)access denied|permission denied|forbidden|unauthorized') {
        return 'PERMISSION'
    }
    if ($Text -match '(?i)cannot connect|error during connect|connection refused|pipe|daemon is not running|context.*not found|no such file') {
        return 'COMMUNICATION'
    }
    if ($Text -match '(?i)no such network|no such volume|no such container|not found|does not exist') {
        return 'MISSING'
    }
    return 'UNKNOWN'
}

function Invoke-DockerReadOnly([string[]]$Arguments) {
    # Only inspection/configuration commands are called by this template.
    # The script never invokes compose up, pull, build, SQL, or application code.
    $output = @(& docker --context $Context @Arguments 2>&1 | ForEach-Object { [string]$_ })
    [pscustomobject]@{
        ExitCode = $LASTEXITCODE
        Output = $output
        Text = (($output -join "`n").Trim())
    }
}

function Test-PathUnder([string]$Child, [string]$Parent) {
    $childFull = [System.IO.Path]::GetFullPath($Child).TrimEnd('\') + '\'
    $parentFull = [System.IO.Path]::GetFullPath($Parent).TrimEnd('\') + '\'
    return $childFull.StartsWith($parentFull, [System.StringComparison]::OrdinalIgnoreCase)
}

function Test-AbsolutePath([string]$Path) {
    return [System.IO.Path]::IsPathFullyQualified($Path)
}

function Test-ExternalSecretDirectory {
    if ([string]::IsNullOrWhiteSpace($SecretDirectory)) {
        Add-Blocked 'SECRET_SECURITY_GATE: TH_SEC_SECRET_DIR is required.'
        Set-Check 'SECRET_DIRECTORY_EXTERNAL' 'BLOCKED'
        Set-Check 'SECRET_FILE_PRESENT' 'BLOCKED'
        return
    }
    if (-not (Test-AbsolutePath $SecretDirectory)) {
        Add-Blocked 'SECRET_SECURITY_GATE: secret directory must be absolute.'
        Set-Check 'SECRET_DIRECTORY_EXTERNAL' 'BLOCKED'
        Set-Check 'SECRET_FILE_PRESENT' 'BLOCKED'
        return
    }
    try {
        $dirItem = Get-Item -LiteralPath $SecretDirectory -ErrorAction Stop
        $dirFull = $dirItem.FullName
        $passwordPath = Join-Path $dirFull 'mariadb-root-password'
        $passwordItem = Get-Item -LiteralPath $passwordPath -ErrorAction Stop

        if ($dirItem.Attributes -band [System.IO.FileAttributes]::ReparsePoint) {
            Add-Blocked 'SECRET_SECURITY_GATE: secret directory is a reparse point.'
        }
        if ($passwordItem.Attributes -band [System.IO.FileAttributes]::ReparsePoint) {
            Add-Blocked 'SECRET_SECURITY_GATE: secret file is a reparse point.'
        }
        if (Test-PathUnder $dirFull $repoRoot) {
            Add-Blocked 'SECRET_SECURITY_GATE: secret directory resolves inside the repository.'
        }
        if ($dirFull -match '(?i)\\(?:xampp|htdocs|bd_clinica|clinica-dolor|production|clinical)(?:\\|$)') {
            Add-Blocked 'SECRET_SECURITY_GATE: secret directory resembles a project, XAMPP, clinical, or production path.'
        }

        # Verify ACLs without reading the secret value. Broad write-capable grants
        # are rejected; ACL lookup failure is fail-closed.
        $acl = Get-Acl -LiteralPath $passwordPath -ErrorAction Stop
        $broadPrincipals = '^(Everyone|BUILTIN\\Users|NT AUTHORITY\\Authenticated Users)$'
        foreach ($rule in @($acl.Access)) {
            $rights = [string]$rule.FileSystemRights
            if ($rule.AccessControlType -eq 'Allow' -and
                $rule.IdentityReference.Value -match $broadPrincipals -and
                $rights -match '(?i)FullControl|Modify|Write|CreateFiles|AppendData') {
                Add-Blocked 'SECRET_SECURITY_GATE: secret file grants broad write-capable access.'
            }
        }
        Set-Check 'SECRET_DIRECTORY_EXTERNAL' $(if (Test-PathUnder $dirFull $repoRoot) { 'BLOCKED' } else { 'PASS' })
        Set-Check 'SECRET_FILE_PRESENT' 'PASS'
        $details['secret_directory'] = $dirFull
    } catch {
        Add-Blocked "SECRET_SECURITY_GATE: cannot verify directory, file, or ACL ($($_.Exception.GetType().Name))."
        Set-Check 'SECRET_DIRECTORY_EXTERNAL' 'BLOCKED'
        Set-Check 'SECRET_FILE_PRESENT' 'BLOCKED'
    }
}

function Test-LocalContext {
    if (-not [string]::IsNullOrWhiteSpace($env:DOCKER_HOST) -or
        -not [string]::IsNullOrWhiteSpace($env:DOCKER_CONTEXT) -or
        -not [string]::IsNullOrWhiteSpace($env:COMPOSE_FILE) -or
        -not [string]::IsNullOrWhiteSpace($env:COMPOSE_PROJECT_NAME)) {
        Add-Blocked 'H01: Docker/Compose environment overrides are set; local identity is ambiguous.'
        Set-Check 'DOCKER_LOCAL_CONTEXT' 'BLOCKED'
        return $false
    }
    if ($Context -ne 'desktop-linux') {
        Add-Blocked 'H01: context must be desktop-linux.'
        Set-Check 'DOCKER_LOCAL_CONTEXT' 'BLOCKED'
        return $false
    }
    $inspection = Invoke-DockerReadOnly @('context', 'inspect', $Context, '--format', '{{json .Endpoints.docker.Host}}')
    if ($inspection.ExitCode -ne 0) {
        Add-Blocked "H01: context inspection failed ($((Get-ErrorClass $inspection.Text)))."
        Set-Check 'DOCKER_LOCAL_CONTEXT' 'BLOCKED'
        return $false
    }
    $endpoint = $inspection.Text.Trim().Trim('"')
    if ($endpoint -ne $allowedEndpoint) {
        Add-Blocked 'H01: context endpoint is not the approved local Docker Desktop named pipe.'
        Set-Check 'DOCKER_LOCAL_CONTEXT' 'BLOCKED'
        return $false
    }
    $details['docker_endpoint'] = $endpoint
    Set-Check 'DOCKER_LOCAL_CONTEXT' 'PASS'
    return $true
}

function Test-DockerDaemon {
    $version = Invoke-DockerReadOnly @('version', '--format', '{{.Client.Version}}|{{.Server.Version}}|{{.Server.Os}}')
    if ($version.ExitCode -ne 0) {
        Add-Blocked "H01: local daemon inspection failed ($((Get-ErrorClass $version.Text)))."
        Set-Check 'DOCKER_DAEMON' 'BLOCKED'
        return
    }
    Set-Check 'DOCKER_DAEMON' 'PASS'
    $details['docker_version'] = $version.Text
}

function Test-ResourceCollision([string]$Kind, [string[]]$Arguments, [string]$DisplayName) {
    $inspection = Invoke-DockerReadOnly $Arguments
    if ($inspection.ExitCode -eq 0) {
        if (-not [string]::IsNullOrWhiteSpace($inspection.Text)) {
            Add-Blocked "H02: $Kind collision exists: $DisplayName."
            return 'BLOCKED'
        }
        return 'PASS'
    }
    $errorClass = Get-ErrorClass $inspection.Text
    if ($errorClass -eq 'MISSING') {
        return 'PASS'
    }
    Add-Blocked "H02: $Kind inspection failed ($errorClass) for $DisplayName."
    return 'BLOCKED'
}

function Test-ComposeEffectiveConfiguration {
    if ($blocked.Count -gt 0) {
        Set-Check 'COMPOSE_EFFECTIVE_CONFIGURATION' 'BLOCKED'
        Set-Check 'NO_PUBLISHED_PORTS' 'BLOCKED'
        Set-Check 'NO_FORBIDDEN_MOUNTS' 'BLOCKED'
        Set-Check 'INTERNAL_NETWORK' 'BLOCKED'
        Add-Blocked 'H03: effective Compose configuration was not inspected because prerequisites failed.'
        return
    }
    if (-not (Test-AbsolutePath $ComposeFile)) {
        Add-Blocked 'H03: Compose file path must be absolute.'
        Set-Check 'COMPOSE_EFFECTIVE_CONFIGURATION' 'BLOCKED'
        return
    }

    $envFile = [System.IO.Path]::GetTempFileName()
    try {
        # Explicit env-file prevents Compose from silently loading the project .env.
        Set-Content -LiteralPath $envFile -Value @(
            "TH_SEC_SECRET_DIR=$SecretDirectory"
        ) -Encoding UTF8
        $composeArgs = @(
            'compose', '--project-name', $projectName, '--env-file', $envFile,
            '--file', $ComposeFile, 'config', '--format', 'json'
        )
        $effective = Invoke-DockerReadOnly $composeArgs
        if ($effective.ExitCode -ne 0) {
            Add-Blocked "H03: effective Compose inspection failed ($((Get-ErrorClass $effective.Text)))."
            Set-Check 'COMPOSE_EFFECTIVE_CONFIGURATION' 'BLOCKED'
            Set-Check 'NO_PUBLISHED_PORTS' 'BLOCKED'
            Set-Check 'NO_FORBIDDEN_MOUNTS' 'BLOCKED'
            Set-Check 'INTERNAL_NETWORK' 'BLOCKED'
            return
        }
        $effectiveConfig = $effective.Text | ConvertFrom-Json -ErrorAction Stop
        $serviceProperties = @($effectiveConfig.services.PSObject.Properties)
        $service = $effectiveConfig.services.db
        $serviceNamesOk = $serviceProperties.Count -eq 1 -and $serviceProperties.Name -contains 'db'
        $imageOk = $null -ne $service -and $service.image -eq $approvedImage
        $pullPolicyOk = $null -ne $service -and $service.pull_policy -eq 'never'
        $portsOk = $null -eq $service.ports -or @($service.ports).Count -eq 0
        $privilegedOk = $null -eq $service.privileged -or $service.privileged -ne $true
        $hostNetworkOk = $null -eq $service.network_mode -or $service.network_mode -ne 'host'
        $commandOk = $null -eq $service.command -and $null -eq $service.entrypoint
        $networkNames = @($service.networks.PSObject.Properties.Name)
        $networkOk = $networkNames -contains 'synthetic_internal'
        $internalOk = $null -ne $effectiveConfig.networks.synthetic_internal -and $effectiveConfig.networks.synthetic_internal.internal -eq $true
        $networkExternalOk = $null -eq $effectiveConfig.networks.synthetic_internal.external -or $effectiveConfig.networks.synthetic_internal.external -ne $true
        $topLevelNetworksOk = @($effectiveConfig.networks.PSObject.Properties).Count -eq 1
        $volumeEntries = @($service.volumes)
        $bindMountOk = $true
        $namedVolumeOk = $false
        foreach ($volume in $volumeEntries) {
            if ($volume.type -eq 'bind' -or [string]$volume.source -match '(?i)(xampp|htdocs|bd_clinica|\.env|docker.sock|/|\\)') {
                $bindMountOk = $false
            }
            if ($volume.type -eq 'volume' -and $volume.source -eq $volumeName) {
                $namedVolumeOk = $true
            }
        }
        $volumeExternalOk = $null -eq $effectiveConfig.volumes.$volumeName.external -or $effectiveConfig.volumes.$volumeName.external -ne $true
        $topLevelVolumesOk = @($effectiveConfig.volumes.PSObject.Properties).Count -eq 1
        $topLevelSecretsOk = @($effectiveConfig.secrets.PSObject.Properties).Count -eq 1
        $secretsOk = @($service.secrets | ForEach-Object { $_.source }) -contains 'mariadb_root_password'
        if (-not $serviceNamesOk -or -not $imageOk -or -not $pullPolicyOk -or -not $portsOk -or
            -not $privilegedOk -or -not $hostNetworkOk -or -not $commandOk -or -not $networkOk -or
            -not $internalOk -or -not $networkExternalOk -or -not $topLevelNetworksOk -or
            -not $bindMountOk -or -not $namedVolumeOk -or -not $volumeExternalOk -or
            -not $topLevelVolumesOk -or -not $topLevelSecretsOk -or -not $secretsOk) {
            Add-Blocked 'H03: effective Compose configuration violates the approved single-service offline contract.'
        }
        Set-Check 'COMPOSE_EFFECTIVE_CONFIGURATION' $(if ($serviceNamesOk -and $imageOk -and $pullPolicyOk -and $commandOk -and $secretsOk) { 'PASS' } else { 'BLOCKED' })
        Set-Check 'NO_PUBLISHED_PORTS' $(if ($portsOk) { 'PASS' } else { 'BLOCKED' })
        Set-Check 'NO_FORBIDDEN_MOUNTS' $(if ($bindMountOk -and $namedVolumeOk -and $volumeExternalOk -and $topLevelVolumesOk -and $privilegedOk -and $hostNetworkOk) { 'PASS' } else { 'BLOCKED' })
        Set-Check 'INTERNAL_NETWORK' $(if ($networkOk -and $internalOk -and $networkExternalOk -and $topLevelNetworksOk) { 'PASS' } else { 'BLOCKED' })
    } catch {
        Add-Blocked "H03: effective Compose output was not parseable ($($_.Exception.GetType().Name))."
        Set-Check 'COMPOSE_EFFECTIVE_CONFIGURATION' 'BLOCKED'
        Set-Check 'NO_PUBLISHED_PORTS' 'BLOCKED'
        Set-Check 'NO_FORBIDDEN_MOUNTS' 'BLOCKED'
        Set-Check 'INTERNAL_NETWORK' 'BLOCKED'
    } finally {
        Remove-Item -LiteralPath $envFile -Force -ErrorAction SilentlyContinue
    }
}

function Test-ComposeSourceStatic {
    if (-not (Test-Path -LiteralPath $ComposeFile -PathType Leaf)) {
        Add-Blocked 'H03: Compose file is missing.'
        return
    }
    $composeText = Get-Content -LiteralPath $ComposeFile -Raw
    $forbiddenPatterns = @(
        '(?im)^\s*ports\s*:',
        '(?im)^\s*privileged\s*:\s*true',
        '(?im)^\s*network_mode\s*:\s*host',
        '(?i)/var/run/docker.sock|dockerDesktopLinuxEngine',
        '(?i)C:\\xampp|xampp\\mysql|bd_clinica|host\.docker\.internal',
        '(?im)^\s*build\s*:',
        '(?im)^\s*pull_policy\s*:\s*(always|missing)',
        '(?im)^\s*external\s*:\s*true'
    )
    foreach ($pattern in $forbiddenPatterns) {
        if ($composeText -match $pattern) {
            Add-Blocked "H03: Compose source contains prohibited setting: $pattern"
        }
    }
    if ($composeText -match [regex]::Escape($blockedDatabase)) {
        Add-Blocked "H03: Compose references protected database: $blockedDatabase"
    }
    if ($composeText -notmatch '(?im)^\s*image\s*:\s*["'']?mariadb:10\.6["'']?\s*$') {
        Add-Blocked 'H05: Compose image must be exactly mariadb:10.6.'
    }
    if ($composeText -notmatch '(?im)^\s*pull_policy\s*:\s*never\s*$') {
        Add-Blocked 'H03: Compose must use pull_policy: never.'
    }
    if ($composeText -notmatch '(?im)^\s*internal\s*:\s*true\s*$') {
        Add-Blocked 'H03: Compose must declare an internal network.'
    }
}

function Test-FixtureManifest {
    if (-not (Test-Path -LiteralPath $FixtureManifest -PathType Leaf)) {
        Add-Blocked 'H06: fixture manifest is missing.'
        Set-Check 'FIXTURE_HASHES' 'BLOCKED'
        Set-Check 'FIXTURE_PROVENANCE' 'BLOCKED'
        return
    }
    $seenPaths = New-Object System.Collections.Generic.HashSet[string]([System.StringComparer]::OrdinalIgnoreCase)
    $hashesOk = $true
    foreach ($line in (Get-Content -LiteralPath $FixtureManifest)) {
        if ([string]::IsNullOrWhiteSpace($line) -or $line.TrimStart().StartsWith('#')) { continue }
        $parts = $line -split '\s+', 2
        if ($parts.Count -ne 2 -or $parts[0] -notmatch '^[A-Fa-f0-9]{64}$') {
            Add-Blocked 'H06: invalid fixture manifest line.'
            $hashesOk = $false
            continue
        }
        $relativePath = $parts[1].Trim().Replace('/', '\')
        if ($relativePath -notmatch '^tests\\fixtures\\' -or $relativePath -match '(^|\\)\.\.(\\|$)') {
            Add-Blocked "H06: fixture path is outside tests/fixtures/: $relativePath"
            $hashesOk = $false
            continue
        }
        if (-not $seenPaths.Add($relativePath)) {
            Add-Blocked "H06: duplicate fixture path: $relativePath"
            $hashesOk = $false
            continue
        }
        $fixturePath = Join-Path $repoRoot $relativePath
        try {
            $fixtureItem = Get-Item -LiteralPath $fixturePath -ErrorAction Stop
            if (-not (Test-PathUnder $fixtureItem.FullName $fixturesRoot) -or
                ($fixtureItem.Attributes -band [System.IO.FileAttributes]::ReparsePoint)) {
                Add-Blocked "H06: fixture path is not canonical inside tests/fixtures/: $relativePath"
                $hashesOk = $false
                continue
            }
            $actual = (Get-FileHash -Algorithm SHA256 -LiteralPath $fixtureItem.FullName).Hash
            if ($actual -ine $parts[0]) {
                Add-Blocked "H06: fixture hash mismatch: $relativePath"
                $hashesOk = $false
            }
        } catch {
            Add-Blocked "H06: fixture file missing or unreadable: $relativePath"
            $hashesOk = $false
        }
    }
    Set-Check 'FIXTURE_HASHES' $(if ($hashesOk -and $seenPaths.Count -gt 0) { 'PASS' } else { 'BLOCKED' })
    # Hash integrity is not provenance. A human/owner evidence gate is required.
    Set-Check 'FIXTURE_PROVENANCE' 'BLOCKED'
    Add-Blocked 'FIXTURE_PROVENANCE_GATE: synthetic provenance evidence is not available to Codex.'
}

try {
    Test-ComposeSourceStatic
    Test-ExternalSecretDirectory
    $localContextOk = Test-LocalContext
    if ($localContextOk) {
        Test-DockerDaemon
    } else {
        Set-Check 'DOCKER_DAEMON' 'BLOCKED'
    }

    if ($Image -ne $approvedImage) {
        Add-Blocked 'H05: requested image is not the approved mariadb:10.6 candidate.'
        Set-Check 'APPROVED_IMAGE' 'BLOCKED'
        Set-Check 'IMAGE_ID' 'BLOCKED'
    } elseif ($checks.DOCKER_DAEMON -eq 'PASS') {
        $imageInspection = Invoke-DockerReadOnly @('image', 'inspect', $approvedImage, '--format', '{{.Id}}|{{json .RepoTags}}')
        if ($imageInspection.ExitCode -ne 0) {
            Add-Blocked 'H05: approved image is not locally available; no pull is attempted.'
            Set-Check 'APPROVED_IMAGE' 'BLOCKED'
            Set-Check 'IMAGE_ID' 'BLOCKED'
        } else {
            $imageParts = $imageInspection.Text -split '\|', 2
            $imageId = $imageParts[0].Trim()
            $details['image_id'] = $imageId
            if ($imageId -notmatch ('^sha256:' + [regex]::Escape($ownerReportedImageIdPrefix))) {
                Add-Blocked 'H05: local image ID does not match the owner-reported approved prefix.'
                Set-Check 'APPROVED_IMAGE' 'BLOCKED'
            } else {
                Set-Check 'APPROVED_IMAGE' 'PASS'
            }
            Set-Check 'IMAGE_ID' $imageId
        }
    } else {
        Set-Check 'APPROVED_IMAGE' 'BLOCKED'
        Set-Check 'IMAGE_ID' 'BLOCKED'
    }

    if ($checks.DOCKER_DAEMON -eq 'PASS') {
        Set-Check 'CONTAINER_COLLISION' (Test-ResourceCollision 'container' @('ps', '-a', '--filter', "name=^$containerName`$", '--format', '{{.ID}}|{{.Names}}') $containerName)
        Set-Check 'NETWORK_COLLISION' (Test-ResourceCollision 'network' @('network', 'inspect', $networkName, '--format', '{{.Id}}|{{.Name}}') $networkName)
        Set-Check 'VOLUME_COLLISION' (Test-ResourceCollision 'volume' @('volume', 'inspect', $volumeName, '--format', '{{.Name}}') $volumeName)
        $projectCollision = Test-ResourceCollision 'Compose project' @('ps', '-a', '--filter', "label=com.docker.compose.project=$projectName", '--format', '{{.ID}}|{{.Names}}') $projectName
        Set-Check 'PROJECT_COLLISION' $projectCollision
    } else {
        Set-Check 'CONTAINER_COLLISION' 'BLOCKED'
        Set-Check 'NETWORK_COLLISION' 'BLOCKED'
        Set-Check 'VOLUME_COLLISION' 'BLOCKED'
        Set-Check 'PROJECT_COLLISION' 'BLOCKED'
    }

    Test-ComposeEffectiveConfiguration
    Test-FixtureManifest

    $details['allowed_databases'] = @($allowedReferenceDatabase, $allowedDisposableDatabase)
    $details['protected_database'] = $blockedDatabase
    $details['context'] = $Context
    $details['project'] = $projectName
    $details['container'] = $containerName
    $details['network'] = $networkName
    $details['volume'] = $volumeName
    $details['blocked_reasons'] = @($blocked)

    $report = [ordered]@{
        PREFLIGHT_TECHNICAL_STATUS = if ($blocked.Count -eq 0) { 'PASS' } else { 'BLOCKED' }
        READY_FOR_INFRASTRUCTURE_CREATION = 'NO'
        DOCKER_LOCAL_CONTEXT = if ($checks.Contains('DOCKER_LOCAL_CONTEXT')) { $checks.DOCKER_LOCAL_CONTEXT } else { 'BLOCKED' }
        DOCKER_DAEMON = if ($checks.Contains('DOCKER_DAEMON')) { $checks.DOCKER_DAEMON } else { 'BLOCKED' }
        APPROVED_IMAGE = if ($checks.Contains('APPROVED_IMAGE')) { $checks.APPROVED_IMAGE } else { 'BLOCKED' }
        IMAGE_ID = if ($checks.Contains('IMAGE_ID')) { $checks.IMAGE_ID } else { 'BLOCKED' }
        CONTAINER_COLLISION = if ($checks.Contains('CONTAINER_COLLISION')) { $checks.CONTAINER_COLLISION } else { 'BLOCKED' }
        NETWORK_COLLISION = if ($checks.Contains('NETWORK_COLLISION')) { $checks.NETWORK_COLLISION } else { 'BLOCKED' }
        VOLUME_COLLISION = if ($checks.Contains('VOLUME_COLLISION')) { $checks.VOLUME_COLLISION } else { 'BLOCKED' }
        PROJECT_COLLISION = if ($checks.Contains('PROJECT_COLLISION')) { $checks.PROJECT_COLLISION } else { 'BLOCKED' }
        COMPOSE_EFFECTIVE_CONFIGURATION = if ($checks.Contains('COMPOSE_EFFECTIVE_CONFIGURATION')) { $checks.COMPOSE_EFFECTIVE_CONFIGURATION } else { 'BLOCKED' }
        NO_PUBLISHED_PORTS = if ($checks.Contains('NO_PUBLISHED_PORTS')) { $checks.NO_PUBLISHED_PORTS } else { 'BLOCKED' }
        NO_FORBIDDEN_MOUNTS = if ($checks.Contains('NO_FORBIDDEN_MOUNTS')) { $checks.NO_FORBIDDEN_MOUNTS } else { 'BLOCKED' }
        INTERNAL_NETWORK = if ($checks.Contains('INTERNAL_NETWORK')) { $checks.INTERNAL_NETWORK } else { 'BLOCKED' }
        SECRET_DIRECTORY_EXTERNAL = if ($checks.Contains('SECRET_DIRECTORY_EXTERNAL')) { $checks.SECRET_DIRECTORY_EXTERNAL } else { 'BLOCKED' }
        SECRET_FILE_PRESENT = if ($checks.Contains('SECRET_FILE_PRESENT')) { $checks.SECRET_FILE_PRESENT } else { 'BLOCKED' }
        FIXTURE_HASHES = if ($checks.Contains('FIXTURE_HASHES')) { $checks.FIXTURE_HASHES } else { 'BLOCKED' }
        FIXTURE_PROVENANCE = if ($checks.Contains('FIXTURE_PROVENANCE')) { $checks.FIXTURE_PROVENANCE } else { 'BLOCKED' }
        DATABASE_CREATION_AUTHORIZED = 'NO'
        DETAILS = $details
    }
    $report | ConvertTo-Json -Depth 8
    if ($blocked.Count -gt 0) { exit 2 }
    exit 0
} catch {
    [ordered]@{
        PREFLIGHT_TECHNICAL_STATUS = 'BLOCKED'
        READY_FOR_INFRASTRUCTURE_CREATION = 'NO'
        DATABASE_CREATION_AUTHORIZED = 'NO'
        BLOCKED_REASONS = @("Unexpected preflight error: $($_.Exception.GetType().Name)")
    } | ConvertTo-Json -Depth 5
    exit 2
}
