[CmdletBinding()]
param([switch]$InstallCertificateTrust)

$ErrorActionPreference = 'Stop'
$repo = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
if ($repo -ne 'C:\xampp\htdocs\clinica-dolor') { throw 'WORKSPACE_NOT_ALLOWLISTED' }

$runtimeRoot = Join-Path $env:USERPROFILE 'AppData\Local\ClinicaDolor\vosk-realtime'
$certificateDir = Join-Path $runtimeRoot 'tls'
New-Item -ItemType Directory -Force -Path $runtimeRoot, $certificateDir | Out-Null
$keyPath = Join-Path $runtimeRoot 'ticket.key'
$certificatePath = Join-Path $certificateDir 'clinica-test-local.pem'
$privateKeyPath = Join-Path $certificateDir 'clinica-test-local-key.pem'
$openssl = 'C:\xampp\apache\bin\openssl.exe'
if (-not (Test-Path -LiteralPath $openssl -PathType Leaf)) { throw 'OPENSSL_NOT_AVAILABLE' }

if (-not (Test-Path -LiteralPath $keyPath -PathType Leaf) -or (Get-Item -LiteralPath $keyPath).Length -ne 32) {
    $secret = New-Object byte[] 32
    $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $rng.GetBytes($secret); [IO.File]::WriteAllBytes($keyPath, $secret) } finally { $rng.Dispose(); [Array]::Clear($secret, 0, $secret.Length) }
}
if ((Get-Item -LiteralPath $keyPath).Length -ne 32) { throw 'TICKET_KEY_INVALID' }

if (-not (Test-Path -LiteralPath $certificatePath -PathType Leaf) -or -not (Test-Path -LiteralPath $privateKeyPath -PathType Leaf) -or (Get-Item -LiteralPath $privateKeyPath).Length -eq 0) {
    $opensslConfig = Join-Path $certificateDir 'certificate-request.cnf'
    $configText = @'
[req]
prompt = no
distinguished_name = subject
x509_extensions = server_ext
[subject]
CN = clinica.test
[server_ext]
subjectAltName = DNS:clinica.test
basicConstraints = critical,CA:FALSE
keyUsage = critical,digitalSignature,keyEncipherment
extendedKeyUsage = serverAuth
'@
    Set-Content -LiteralPath $opensslConfig -Value $configText -Encoding Ascii
    try {
        $stdoutLog = Join-Path $certificateDir 'openssl-stdout.tmp'
        $stderrLog = Join-Path $certificateDir 'openssl-stderr.tmp'
        $process = Start-Process -FilePath $openssl -ArgumentList @('req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-days', '365', '-config', $opensslConfig, '-keyout', $privateKeyPath, '-out', $certificatePath) -WorkingDirectory 'C:\xampp\apache\bin' -WindowStyle Hidden -Wait -PassThru -RedirectStandardOutput $stdoutLog -RedirectStandardError $stderrLog
        $opensslExitCode = [int]$process.ExitCode
        Remove-Item -LiteralPath $stdoutLog, $stderrLog -Force -ErrorAction SilentlyContinue
        if ($opensslExitCode -ne 0) { throw 'LOCAL_CERTIFICATE_GENERATION_FAILED' }
    } finally {
        Remove-Item -LiteralPath $opensslConfig -Force -ErrorAction SilentlyContinue
    }
}

$certificate = New-Object Security.Cryptography.X509Certificates.X509Certificate2($certificatePath)
if ($certificate.Subject -notmatch 'CN=clinica\.test' -or $certificate.GetNameInfo([Security.Cryptography.X509Certificates.X509NameType]::DnsName, $false) -ne 'clinica.test') {
    throw 'LOCAL_CERTIFICATE_IDENTITY_INVALID'
}
$thumbprint = $certificate.Thumbprint

$validationDir = Join-Path $runtimeRoot 'config-validation'
New-Item -ItemType Directory -Force -Path (Join-Path $validationDir 'sessions'), (Join-Path $validationDir 'tmp') | Out-Null
$configTemplate = Get-Content -LiteralPath (Join-Path $PSScriptRoot 'https-vosk-apache.conf') -Raw
$validationConf = $configTemplate.Replace('__VOSK_RUNTIME__', $validationDir.Replace('\', '/')).
    Replace('__VOSK_CERT__', $certificatePath.Replace('\', '/')).
    Replace('__VOSK_CERT_KEY__', $privateKeyPath.Replace('\', '/')).
    Replace('__VOSK_ROOT__', $runtimeRoot.Replace('\', '/'))
$validationConfPath = Join-Path $validationDir 'httpd.conf'
Set-Content -LiteralPath $validationConfPath -Value $validationConf -Encoding UTF8
$validationOut = Join-Path $validationDir 'httpd-check.stdout'
$validationErr = Join-Path $validationDir 'httpd-check.stderr'
$httpd = 'C:\xampp\apache\bin\httpd.exe'
try {
    $check = Start-Process -FilePath $httpd -ArgumentList @('-f', ('"' + $validationConfPath + '"'), '-d', '"C:\xampp\apache"', '-t') -WorkingDirectory 'C:\xampp\apache' -WindowStyle Hidden -Wait -PassThru -RedirectStandardOutput $validationOut -RedirectStandardError $validationErr
    if ([int]$check.ExitCode -ne 0) { throw 'ISOLATED_APACHE_CONFIG_INVALID' }
} finally {
    Remove-Item -LiteralPath $validationDir -Recurse -Force -ErrorAction SilentlyContinue
}

if ($InstallCertificateTrust) {
    $store = New-Object Security.Cryptography.X509Certificates.X509Store('Root', 'CurrentUser')
    try {
        $store.Open([Security.Cryptography.X509Certificates.OpenFlags]::ReadWrite)
        $alreadyPresent = @($store.Certificates | Where-Object Thumbprint -eq $thumbprint).Count -gt 0
        if (-not $alreadyPresent) { $store.Add($certificate) }
    } finally { $store.Close() }
}

[ordered]@{
    status = 'PREPARED'
    runtime_root = $runtimeRoot
    ticket_key = '32_BYTES_PRESENT'
    certificate_subject = $certificate.Subject
    certificate_thumbprint = $thumbprint
    certificate_trust = if ($InstallCertificateTrust) { 'CURRENT_USER_ROOT_INSTALLED_OR_ALREADY_PRESENT' } else { 'NOT_CHANGED' }
} | ConvertTo-Json -Compress
