[CmdletBinding()]
param(
    [switch] $ParserSelfTest
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

function Test-IsReparsePoint {
    param(
        [Parameter(Mandatory = $true)]
        [System.IO.FileSystemInfo] $Item
    )

    return (($Item.Attributes -band [System.IO.FileAttributes]::ReparsePoint) -ne 0)
}

function Write-NewFileBytes {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Path,
        [Parameter(Mandatory = $true)]
        [byte[]] $Bytes
    )

    $stream = $null
    try {
        $stream = [System.IO.File]::Open(
            $Path,
            [System.IO.FileMode]::CreateNew,
            [System.IO.FileAccess]::Write,
            [System.IO.FileShare]::None
        )
        $stream.Write($Bytes, 0, $Bytes.Length)
    }
    finally {
        if ($null -ne $stream) {
            $stream.Dispose()
        }
    }
}

function New-SyntheticPdfBytes {
    $encoding = [System.Text.Encoding]::ASCII
    $lineFeed = "`n"
    $header = "%PDF-1.4$lineFeed% Synthetic local HTTP probe$lineFeed"
    $streamText = "BT /F1 12 Tf 36 160 Td (Synthetic COFEPRIS probe) Tj ET$lineFeed"
    $streamLength = $encoding.GetByteCount($streamText)

    $object1 = "1 0 obj$lineFeed<< /Type /Catalog /Pages 2 0 R >>$lineFeed" +
        "endobj$lineFeed"
    $object2 = "2 0 obj$lineFeed<< /Type /Pages /Kids [3 0 R] /Count 1 >>$lineFeed" +
        "endobj$lineFeed"
    $object3 = "3 0 obj$lineFeed<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 200] " +
        "/Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>$lineFeed" +
        "endobj$lineFeed"
    $object4 = "4 0 obj$lineFeed<< /Length $streamLength >>$lineFeed" +
        "stream$lineFeed" + $streamText + "endstream$lineFeed" +
        "endobj$lineFeed"
    $object5 = "5 0 obj$lineFeed<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>$lineFeed" +
        "endobj$lineFeed"
    $objects = @($object1, $object2, $object3, $object4, $object5)

    $offsets = @()
    $cursor = $encoding.GetByteCount($header)
    foreach ($object in $objects) {
        $offsets += $cursor
        $cursor += $encoding.GetByteCount($object)
    }

    $xrefOffset = $cursor
    $xrefBuilder = New-Object System.Text.StringBuilder
    [void] $xrefBuilder.Append("xref$lineFeed")
    [void] $xrefBuilder.Append("0 6$lineFeed")
    [void] $xrefBuilder.Append("0000000000 65535 f $lineFeed")
    foreach ($offset in $offsets) {
        [void] $xrefBuilder.Append(("{0:D10} 00000 n $lineFeed" -f $offset))
    }
    [void] $xrefBuilder.Append("trailer$lineFeed")
    [void] $xrefBuilder.Append("<< /Size 6 /Root 1 0 R >>$lineFeed")
    [void] $xrefBuilder.Append("startxref$lineFeed")
    [void] $xrefBuilder.Append("$xrefOffset$lineFeed")
    [void] $xrefBuilder.Append("%%EOF$lineFeed")

    $pdfText = $header + ($objects -join '') + $xrefBuilder.ToString()
    return $encoding.GetBytes($pdfText)
}

function Get-HeaderValue {
    param(
        [AllowNull()]
        [AllowEmptyCollection()]
        [AllowEmptyString()]
        [string[]] $HeaderLines,
        [Parameter(Mandatory = $true)]
        [string] $Name
    )

    $prefix = $Name + ':'
    foreach ($line in @($HeaderLines)) {
        if ([string]::IsNullOrEmpty($line)) {
            continue
        }
        if ($line.StartsWith($prefix, [System.StringComparison]::OrdinalIgnoreCase)) {
            return $line.Substring($prefix.Length).Trim()
        }
    }

    return 'NOT_VERIFIED'
}

function Get-FinalHttpHeaderBlock {
    param(
        [AllowNull()]
        [AllowEmptyCollection()]
        [AllowEmptyString()]
        [string[]] $HeaderLines
    )

    $blocks = @()
    $currentStatusLine = $null
    $currentHeaderLines = @()
    $sawStatusLine = $false

    foreach ($line in @($HeaderLines)) {
        $lineText = if ($null -eq $line) { '' } else { [string]$line }
        if ($lineText -match '^\s*HTTP/(?<version>1\.[01]|2(?:\.\d+)?)\s+(?<status>\d{3})(?:\s|$)') {
            if ($sawStatusLine) {
                $blocks += [pscustomobject]@{
                    StatusLine = $currentStatusLine
                    HeaderLines = [string[]]$currentHeaderLines
                }
            }
            $sawStatusLine = $true
            $currentStatusLine = $lineText.Trim()
            $currentHeaderLines = @()
            continue
        }

        if ($sawStatusLine) {
            $currentHeaderLines += $lineText
        }
    }

    if ($sawStatusLine) {
        $blocks += [pscustomobject]@{
            StatusLine = $currentStatusLine
            HeaderLines = [string[]]$currentHeaderLines
        }
    }

    if ($blocks.Count -eq 0) {
        return [pscustomobject]@{
            ParseStatus = 'NOT_VERIFIED'
            ParseMessage = 'HTTP_HEADERS_ABSENT_OR_MALFORMED'
            StatusLine = $null
            HeaderLines = [string[]]@()
        }
    }

    $finalBlock = $blocks[$blocks.Count - 1]
    $validHeaderLines = @($finalBlock.HeaderLines | Where-Object {
        (-not [string]::IsNullOrEmpty($_)) -and ($_ -match '^[^:]+:\s*.*$')
    })

    if ($validHeaderLines.Count -eq 0) {
        return [pscustomobject]@{
            ParseStatus = 'NOT_VERIFIED'
            ParseMessage = 'HTTP_HEADER_LINES_ABSENT_OR_MALFORMED'
            StatusLine = $finalBlock.StatusLine
            HeaderLines = [string[]]@()
        }
    }

    return [pscustomobject]@{
        ParseStatus = 'PASS'
        ParseMessage = 'FINAL_HTTP_HEADER_BLOCK_SELECTED'
        StatusLine = $finalBlock.StatusLine
        HeaderLines = [string[]]$validHeaderLines
    }
}

function Get-SafeErrorMessage {
    param(
        [Parameter(Mandatory = $true)]
        [object] $ErrorRecord,
        [Parameter(Mandatory = $true)]
        [string[]] $SensitiveValues
    )

    $message = [string]$ErrorRecord.Exception.Message
    foreach ($sensitiveValue in $SensitiveValues) {
        if (-not [string]::IsNullOrWhiteSpace($sensitiveValue)) {
            $message = $message -replace [regex]::Escape($sensitiveValue), '<redacted>'
        }
    }
    $message = $message -replace '[\r\n]+', ' '
    if ([string]::IsNullOrWhiteSpace($message)) {
        return 'Unspecified probe error.'
    }
    if ($message.Length -gt 240) {
        return $message.Substring(0, 240)
    }
    return $message
}

function Get-FailureCodeForError {
    param(
        [Parameter(Mandatory = $true)]
        [object] $ErrorRecord,
        [Parameter(Mandatory = $true)]
        [string] $Stage
    )

    $rawMessage = [string]$ErrorRecord.Exception.Message
    if ($rawMessage -match '^[A-Z][A-Z0-9_]+$') {
        return $rawMessage
    }

    switch ($Stage) {
        'PROJECT_ROOT_VALIDATION' { return 'PROJECT_ROOT_VALIDATION_FAILED' }
        'PROJECT_DIRECTORY_VALIDATION' { return 'PROJECT_DIRECTORY_VALIDATION_FAILED' }
        'STORAGE_DIRECTORY_VALIDATION' { return 'STORAGE_DIRECTORY_VALIDATION_FAILED' }
        'CURL_VALIDATION' { return 'CURL_NOT_AVAILABLE' }
        'IDENTIFIER_GENERATION' { return 'PROBE_IDENTIFIER_GENERATION_FAILED' }
        'PDF_GENERATION' { return 'SYNTHETIC_PDF_GENERATION_FAILED' }
        'PROBE_DIRECTORY_CREATE' { return 'PROBE_DIRECTORY_CREATION_FAILED' }
        'SYNTHETIC_FILE_CREATE' { return 'SYNTHETIC_FILE_CREATION_FAILED' }
        'TEMP_DIRECTORY_CREATE' { return 'TEMP_DIRECTORY_CREATION_FAILED' }
        'SYNTHETIC_HASH' { return 'SYNTHETIC_HASH_FAILED' }
        'HTTP_GET' { return 'HTTP_REQUEST_FAILED' }
        'RESPONSE_PARSE' { return 'HTTP_RESPONSE_PARSE_FAILED' }
        'RESPONSE_HASH' { return 'RESPONSE_HASH_FAILED' }
        'ROUTE_VALIDATION' { return 'ROUTE_VALIDATION_FAILED' }
        default { return 'UNEXPECTED_ERROR' }
    }
}

function Invoke-HttpRouteProbe {
    param(
        [Parameter(Mandatory = $true)]
        [string] $RouteName,
        [Parameter(Mandatory = $true)]
        [string] $ProbeUrl,
        [Parameter(Mandatory = $true)]
        [string] $CurlPath,
        [Parameter(Mandatory = $true)]
        [string] $ResponsePath,
        [Parameter(Mandatory = $true)]
        [string] $HeadersPath,
        [Parameter(Mandatory = $true)]
        [string] $CurlErrorPath,
        [Parameter(Mandatory = $true)]
        [string] $SyntheticHash
    )

    $result = [ordered]@{
        RouteName = $RouteName
        Url = $ProbeUrl
        HttpStatus = 'NOT_EXECUTED'
        ContentType = 'NOT_VERIFIED'
        CurlExitCode = 'NOT_EXECUTED'
        SyntheticBytesMatch = 'NOT_VERIFIED'
        DirectHttpAccess = 'NOT_VERIFIED'
        ResultClassification = 'NOT_EXECUTED'
        HeaderParseStatus = 'NOT_EXECUTED'
        HeaderParseMessage = 'NOT_EXECUTED'
        ErrorCode = 'NONE'
    }

    try {
        $curlArguments = @(
            '-q',
            '--silent',
            '--show-error',
            '--request', 'GET',
            '--output', $ResponsePath,
            '--dump-header', $HeadersPath,
            '--write-out', '%{http_code}',
            '--connect-timeout', '5',
            '--max-time', '20',
            '--max-redirs', '0',
            '--noproxy', '*',
            '--resolve', 'clinica.test:80:127.0.0.1',
            '--proto', '=http',
            '--url', $ProbeUrl
        )

        $curlOutput = (& $CurlPath @curlArguments 2> $CurlErrorPath | Out-String).Trim()
        $result.CurlExitCode = $LASTEXITCODE
        if ([int]$result.CurlExitCode -ne 0) {
            $result.ResultClassification = 'FAIL'
            $result.ErrorCode = 'CURL_REQUEST_FAILED'
            return [pscustomobject]$result
        }

        if (($curlOutput -match '^\d{3}$') -and ($curlOutput -ne '000')) {
            $result.HttpStatus = $curlOutput
        }
        else {
            $result.ResultClassification = 'FAIL'
            $result.ErrorCode = 'HTTP_STATUS_NOT_PARSED'
            return [pscustomobject]$result
        }

        if (Test-Path -LiteralPath $HeadersPath) {
            $headerLines = @(Get-Content -LiteralPath $HeadersPath -ErrorAction Stop)
            $headerBlock = Get-FinalHttpHeaderBlock -HeaderLines $headerLines
            $result.HeaderParseStatus = $headerBlock.ParseStatus
            $result.HeaderParseMessage = $headerBlock.ParseMessage
            $result.ContentType = Get-HeaderValue -HeaderLines $headerBlock.HeaderLines -Name 'Content-Type'
        }
        else {
            $result.HeaderParseStatus = 'NOT_VERIFIED'
            $result.HeaderParseMessage = 'HTTP_HEADERS_FILE_UNAVAILABLE'
        }

        if ($result.HttpStatus -eq '200') {
            if (-not (Test-Path -LiteralPath $ResponsePath)) {
                $result.ResultClassification = 'FAIL'
                $result.ErrorCode = 'HTTP_RESPONSE_BODY_UNAVAILABLE'
                return [pscustomobject]$result
            }

            $responseHash = (Get-FileHash -LiteralPath $ResponsePath -Algorithm SHA256).Hash
            $result.SyntheticBytesMatch = [string]::Equals(
                $SyntheticHash,
                $responseHash,
                [System.StringComparison]::OrdinalIgnoreCase
            )
            $result.DirectHttpAccess = [bool]$result.SyntheticBytesMatch
            if ($result.SyntheticBytesMatch) {
                $result.ResultClassification = 'BYPASS_CONFIRMED'
            }
            else {
                $result.ResultClassification = 'INCONCLUSIVE'
                $result.ErrorCode = 'HTTP_200_BYTES_MISMATCH'
            }
            return [pscustomobject]$result
        }

        $result.SyntheticBytesMatch = $false
        $result.DirectHttpAccess = $false
        if (($result.HttpStatus -eq '401') -or ($result.HttpStatus -eq '403')) {
            $result.ResultClassification = 'DENIED_VERIFIED'
        }
        else {
            $result.ResultClassification = 'INCONCLUSIVE'
            $result.ErrorCode = 'HTTP_STATUS_NOT_PROTECTIVE'
        }

        return [pscustomobject]$result
    }
    catch {
        $result.ResultClassification = 'FAIL'
        $result.ErrorCode = 'ROUTE_PROBE_ERROR'
        return [pscustomobject]$result
    }
}

if ($ParserSelfTest) {
    try {
        $emptyBlock = Get-FinalHttpHeaderBlock -HeaderLines @()
        $emptyValue = Get-HeaderValue -HeaderLines @('') -Name 'Content-Type'
        if (($emptyBlock.ParseStatus -ne 'NOT_VERIFIED') -or
            ($emptyBlock.ParseMessage -ne 'HTTP_HEADERS_ABSENT_OR_MALFORMED') -or
            ($emptyValue -ne 'NOT_VERIFIED')) {
            throw 'EMPTY_HEADER_TEST_FAILED'
        }

        $syntheticHeaderLines = @(
            'HTTP/1.1 100 Continue',
            '',
            'Content-Type: text/plain',
            '',
            'HTTP/2 200',
            '',
            'Content-Type: application/pdf',
            'Content-Length: 4',
            ''
        )
        $finalBlock = Get-FinalHttpHeaderBlock -HeaderLines $syntheticHeaderLines
        $finalContentType = Get-HeaderValue -HeaderLines $finalBlock.HeaderLines -Name 'Content-Type'
        if (($finalBlock.ParseStatus -ne 'PASS') -or
            ($finalBlock.StatusLine -notmatch '^HTTP/2 200') -or
            ($finalContentType -ne 'application/pdf')) {
            throw 'FINAL_HEADER_BLOCK_TEST_FAILED'
        }

        Write-Output 'PARSER_SELF_TEST=PASS'
        exit 0
    }
    catch {
        Write-Output 'PARSER_SELF_TEST=FAIL'
        exit 1
    }
}

$expectedProjectRoot = 'C:\xampp\htdocs\clinica-dolor'
$scriptProjectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$storageRoot = Join-Path $scriptProjectRoot 'public\storage\cofepris'
$probeDirectoryPath = $null
$probeFilePath = $null
$tempRootPath = $null
$responsePath = $null
$headersPath = $null
$curlErrorPath = $null
$curlPath = $null
$probeUrl = $null
$routeDefinitions = @()
$routeResults = @()
$temporaryArtifacts = @()
$probeDirectoryCreated = $false
$probeFileCreated = $false
$tempRootCreated = $false
$executionStage = 'INITIALIZATION'
$httpStatus = 'NOT_EXECUTED'
$syntheticBytesMatch = 'NOT_VERIFIED'
$directHttpAccess = 'NOT_VERIFIED'
$contentType = 'NOT_VERIFIED'
$headerParseStatus = 'NOT_EXECUTED'
$headerParseMessage = 'NOT_EXECUTED'
$curlExitCode = 'NOT_EXECUTED'
$cleanupStatus = 'NOT_STARTED'
$executionFailed = $false
$failureCode = 'NONE'
$errorType = 'NONE'
$errorMessage = 'NONE'
$processExitCode = 'NOT_SET'
$cleanupErrors = @()

try {
    $executionStage = 'PROJECT_ROOT_VALIDATION'
    if (-not [System.StringComparer]::OrdinalIgnoreCase.Equals(
        $scriptProjectRoot.TrimEnd('\'),
        $expectedProjectRoot.TrimEnd('\')
    )) {
        throw 'PROJECT_ROOT_MISMATCH'
    }

    $executionStage = 'PROJECT_DIRECTORY_VALIDATION'
    $projectItem = Get-Item -LiteralPath $scriptProjectRoot -ErrorAction Stop
    if ((-not $projectItem.PSIsContainer) -or (Test-IsReparsePoint -Item $projectItem)) {
        throw 'PROJECT_ROOT_UNSAFE'
    }

    $executionStage = 'STORAGE_DIRECTORY_VALIDATION'
    $storageItem = Get-Item -LiteralPath $storageRoot -ErrorAction Stop
    if ((-not $storageItem.PSIsContainer) -or (Test-IsReparsePoint -Item $storageItem)) {
        throw 'STORAGE_ROOT_UNSAFE'
    }

    $executionStage = 'CURL_VALIDATION'
    $curlCommand = Get-Command -Name 'curl.exe' -CommandType Application -ErrorAction Stop
    $curlPath = $curlCommand.Source
    if ([string]::IsNullOrWhiteSpace($curlPath)) {
        $curlPath = $curlCommand.Path
    }

    $executionStage = 'IDENTIFIER_GENERATION'
    $probeGuid = [guid]::NewGuid().ToString('N')
    $probeDirectoryName = '__cofepris_http_probe_' + $probeGuid
    $probeFileName = 'synthetic-probe.pdf'
    $probeDirectoryPath = Join-Path $storageRoot $probeDirectoryName
    $probeFilePath = Join-Path $probeDirectoryPath $probeFileName
    $tempRootPath = Join-Path ([System.IO.Path]::GetTempPath()) ('cofepris-http-probe-' + $probeGuid)
    $responsePath = Join-Path $tempRootPath 'response.bin'
    $headersPath = Join-Path $tempRootPath 'headers.txt'
    $curlErrorPath = Join-Path $tempRootPath 'curl-error.txt'

    if (Test-Path -LiteralPath $probeDirectoryPath) {
        throw 'PROBE_DIRECTORY_ALREADY_EXISTS'
    }
    if (Test-Path -LiteralPath $tempRootPath) {
        throw 'TEMP_DIRECTORY_ALREADY_EXISTS'
    }

    $executionStage = 'PDF_GENERATION'
    $probeUrl = 'http://clinica.test/public/storage/cofepris/' +
        $probeDirectoryName + '/' + $probeFileName
    $probePdfBytes = New-SyntheticPdfBytes

    $executionStage = 'PROBE_DIRECTORY_CREATE'
    $createdProbeDirectory = New-Item -ItemType Directory -Path $probeDirectoryPath -ErrorAction Stop
    $probeDirectoryCreated = $true
    if ((-not $createdProbeDirectory.PSIsContainer) -or (Test-IsReparsePoint -Item $createdProbeDirectory)) {
        throw 'PROBE_DIRECTORY_UNSAFE'
    }

    $executionStage = 'SYNTHETIC_FILE_CREATE'
    Write-NewFileBytes -Path $probeFilePath -Bytes $probePdfBytes
    $probeFileCreated = $true
    $probeFileItem = Get-Item -LiteralPath $probeFilePath -ErrorAction Stop
    if ($probeFileItem.PSIsContainer -or (Test-IsReparsePoint -Item $probeFileItem)) {
        throw 'PROBE_FILE_UNSAFE'
    }

    $executionStage = 'TEMP_DIRECTORY_CREATE'
    $createdTempRoot = New-Item -ItemType Directory -Path $tempRootPath -ErrorAction Stop
    $tempRootCreated = $true
    if ((-not $createdTempRoot.PSIsContainer) -or (Test-IsReparsePoint -Item $createdTempRoot)) {
        throw 'TEMP_DIRECTORY_UNSAFE'
    }

    $executionStage = 'SYNTHETIC_HASH'
    $syntheticHash = (Get-FileHash -LiteralPath $probeFilePath -Algorithm SHA256).Hash

    $routeDefinitions = @(
        [pscustomobject]@{
            Name = 'CANONICAL'
            Url = $probeUrl
            ResponsePath = Join-Path $tempRootPath 'canonical-response.bin'
            HeadersPath = Join-Path $tempRootPath 'canonical-headers.txt'
            CurlErrorPath = Join-Path $tempRootPath 'canonical-curl-error.txt'
        },
        [pscustomobject]@{
            Name = 'REWRITTEN'
            Url = 'http://clinica.test/storage/cofepris/' +
                $probeDirectoryName + '/' + $probeFileName
            ResponsePath = Join-Path $tempRootPath 'rewritten-response.bin'
            HeadersPath = Join-Path $tempRootPath 'rewritten-headers.txt'
            CurlErrorPath = Join-Path $tempRootPath 'rewritten-curl-error.txt'
        }
    )
    $responsePath = $routeDefinitions[0].ResponsePath
    $headersPath = $routeDefinitions[0].HeadersPath
    $curlErrorPath = $routeDefinitions[0].CurlErrorPath
    foreach ($routeDefinition in $routeDefinitions) {
        $temporaryArtifacts += [pscustomobject]@{
            ResponsePath = $routeDefinition.ResponsePath
            HeadersPath = $routeDefinition.HeadersPath
            CurlErrorPath = $routeDefinition.CurlErrorPath
        }
    }

    $executionStage = 'HTTP_GET'
    foreach ($routeDefinition in $routeDefinitions) {
        $routeResults += Invoke-HttpRouteProbe `
            -RouteName $routeDefinition.Name `
            -ProbeUrl $routeDefinition.Url `
            -CurlPath $curlPath `
            -ResponsePath $routeDefinition.ResponsePath `
            -HeadersPath $routeDefinition.HeadersPath `
            -CurlErrorPath $routeDefinition.CurlErrorPath `
            -SyntheticHash $syntheticHash
    }

    $executionStage = 'ROUTE_VALIDATION'
    $canonicalResult = $routeResults[0]
    $httpStatus = $canonicalResult.HttpStatus
    $contentType = $canonicalResult.ContentType
    $headerParseStatus = $canonicalResult.HeaderParseStatus
    $headerParseMessage = $canonicalResult.HeaderParseMessage
    $curlExitCode = $canonicalResult.CurlExitCode

    $hasByteMatch = @($routeResults | Where-Object { $_.SyntheticBytesMatch -eq $true }).Count -gt 0
    $hasUnverifiedBytes = @($routeResults | Where-Object { $_.SyntheticBytesMatch -eq 'NOT_VERIFIED' }).Count -gt 0
    if ($hasByteMatch) {
        $syntheticBytesMatch = $true
        $directHttpAccess = $true
    }
    elseif ($hasUnverifiedBytes) {
        $syntheticBytesMatch = 'NOT_VERIFIED'
        $directHttpAccess = 'NOT_VERIFIED'
    }
    else {
        $syntheticBytesMatch = $false
        $directHttpAccess = $false
    }

    $nonDeniedResults = @($routeResults | Where-Object {
        $_.ResultClassification -ne 'DENIED_VERIFIED'
    })
    if ($nonDeniedResults.Count -gt 0) {
        $bypassResults = @($routeResults | Where-Object {
            $_.ResultClassification -eq 'BYPASS_CONFIRMED'
        })
        $failedResults = @($routeResults | Where-Object {
            $_.ResultClassification -eq 'FAIL'
        })
        if ($bypassResults.Count -gt 0) {
            throw 'DIRECT_HTTP_ACCESS_CONFIRMED'
        }
        if ($failedResults.Count -gt 0) {
            throw 'ROUTE_PROBE_FAILED'
        }
        throw 'ROUTE_PROTECTION_INCONCLUSIVE'
    }
}
catch {
    $executionFailed = $true
    $failureCode = Get-FailureCodeForError -ErrorRecord $_ -Stage $executionStage
    $errorType = $_.Exception.GetType().Name
    $errorMessage = Get-SafeErrorMessage -ErrorRecord $_ -SensitiveValues @(
        $expectedProjectRoot,
        $storageRoot,
        $probeDirectoryPath,
        $probeFilePath,
        $tempRootPath,
        $responsePath,
        $headersPath,
        $curlErrorPath,
        $probeUrl
    )
}
finally {
    if ($probeFileCreated -and (Test-Path -LiteralPath $probeFilePath)) {
        try {
            $probeFileItem = Get-Item -LiteralPath $probeFilePath -ErrorAction Stop
            if ($probeFileItem.PSIsContainer -or (Test-IsReparsePoint -Item $probeFileItem)) {
                throw 'SYNTHETIC_FILE_CHANGED'
            }
            Remove-Item -LiteralPath $probeFilePath -Force -ErrorAction Stop
        }
        catch {
            $cleanupErrors += 'SYNTHETIC_FILE_NOT_REMOVED'
        }
    }

    if ($probeDirectoryCreated -and (Test-Path -LiteralPath $probeDirectoryPath)) {
        try {
            $probeDirectoryItem = Get-Item -LiteralPath $probeDirectoryPath -ErrorAction Stop
            $probeChildren = @(Get-ChildItem -LiteralPath $probeDirectoryPath -Force -ErrorAction Stop)
            if ((-not $probeDirectoryItem.PSIsContainer) -or
                (Test-IsReparsePoint -Item $probeDirectoryItem) -or
                ($probeChildren.Count -ne 0)) {
                throw 'PROBE_DIRECTORY_NOT_EMPTY_OR_CHANGED'
            }
            Remove-Item -LiteralPath $probeDirectoryPath -Force -ErrorAction Stop
        }
        catch {
            $cleanupErrors += 'PROBE_DIRECTORY_NOT_REMOVED'
        }
    }

    foreach ($temporaryArtifact in @($temporaryArtifacts)) {
        foreach ($temporaryFile in @(
            $temporaryArtifact.ResponsePath,
            $temporaryArtifact.HeadersPath,
            $temporaryArtifact.CurlErrorPath
        )) {
            if (-not [string]::IsNullOrWhiteSpace($temporaryFile) -and (Test-Path -LiteralPath $temporaryFile)) {
                try {
                    $temporaryItem = Get-Item -LiteralPath $temporaryFile -ErrorAction Stop
                    if ($temporaryItem.PSIsContainer -or (Test-IsReparsePoint -Item $temporaryItem)) {
                        throw 'TEMPORARY_FILE_CHANGED'
                    }
                    Remove-Item -LiteralPath $temporaryFile -Force -ErrorAction Stop
                }
                catch {
                    $cleanupErrors += 'TEMPORARY_RESPONSE_NOT_REMOVED'
                }
            }
        }
    }

    if ($tempRootCreated -and (Test-Path -LiteralPath $tempRootPath)) {
        try {
            $tempRootItem = Get-Item -LiteralPath $tempRootPath -ErrorAction Stop
            $tempChildren = @(Get-ChildItem -LiteralPath $tempRootPath -Force -ErrorAction Stop)
            if ((-not $tempRootItem.PSIsContainer) -or
                (Test-IsReparsePoint -Item $tempRootItem) -or
                ($tempChildren.Count -ne 0)) {
                throw 'TEMP_DIRECTORY_NOT_EMPTY_OR_CHANGED'
            }
            Remove-Item -LiteralPath $tempRootPath -Force -ErrorAction Stop
        }
        catch {
            $cleanupErrors += 'TEMP_DIRECTORY_NOT_REMOVED'
        }
    }

    if ($cleanupErrors.Count -eq 0) {
        $cleanupStatus = 'PASS'
    }
    else {
        $cleanupStatus = 'FAIL'
        if (-not $executionFailed) {
            $executionFailed = $true
            $executionStage = 'CLEANUP'
            $failureCode = 'CLEANUP_FAILED'
            $errorType = 'CleanupException'
            $errorMessage = 'One or more temporary probe artifacts could not be removed.'
        }
    }
}

if (-not $executionFailed) {
    $executionStage = 'COMPLETE'
    $processExitCode = 0
}
else {
    $processExitCode = 1
}

Write-Output ('EXECUTION_STAGE=' + $executionStage)
Write-Output ('FAILURE_CODE=' + $failureCode)
Write-Output ('ERROR_TYPE=' + $errorType)
Write-Output ('ERROR_MESSAGE=' + $errorMessage)
Write-Output ('HTTP_STATUS=' + $httpStatus)
Write-Output ('SYNTHETIC_BYTES_MATCH=' + $syntheticBytesMatch)
Write-Output ('DIRECT_HTTP_ACCESS=' + $directHttpAccess)
Write-Output ('CONTENT_TYPE=' + $contentType)
Write-Output ('HEADER_PARSE_STATUS=' + $headerParseStatus)
Write-Output ('HEADER_PARSE_MESSAGE=' + $headerParseMessage)
Write-Output ('CURL_EXIT_CODE=' + $curlExitCode)
Write-Output ('ROUTE_COUNT=' + $routeResults.Count)
foreach ($routeResult in @($routeResults)) {
    $routePrefix = [string]$routeResult.RouteName
    Write-Output ($routePrefix + '_URL=' + $routeResult.Url)
    Write-Output ($routePrefix + '_HTTP_STATUS=' + $routeResult.HttpStatus)
    Write-Output ($routePrefix + '_CONTENT_TYPE=' + $routeResult.ContentType)
    Write-Output ($routePrefix + '_CURL_EXIT_CODE=' + $routeResult.CurlExitCode)
    Write-Output ($routePrefix + '_SYNTHETIC_BYTES_MATCH=' + $routeResult.SyntheticBytesMatch)
    Write-Output ($routePrefix + '_DIRECT_HTTP_ACCESS=' + $routeResult.DirectHttpAccess)
    Write-Output ($routePrefix + '_RESULT_CLASSIFICATION=' + $routeResult.ResultClassification)
    Write-Output ($routePrefix + '_ERROR_CODE=' + $routeResult.ErrorCode)
    Write-Output ($routePrefix + '_HEADER_PARSE_STATUS=' + $routeResult.HeaderParseStatus)
    Write-Output ($routePrefix + '_HEADER_PARSE_MESSAGE=' + $routeResult.HeaderParseMessage)
}
Write-Output ('CLEANUP_STATUS=' + $cleanupStatus)
Write-Output ('PROCESS_EXIT_CODE=' + $processExitCode)

exit $processExitCode
