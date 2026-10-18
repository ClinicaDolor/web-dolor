[CmdletBinding()]
param(
    [string]$BaseUrl = 'http://clinica.test',
    [int]$TimeoutSec = 8,
    [switch]$InteractiveDoctorLogin,
    [switch]$RunAuthenticatedReadOnly,
    [int]$PatientId,
    [string]$Reference,
    [int]$NoteId,
    [int]$LaboratoryId,
    [switch]$AllowSyntheticMutations,
    [string]$FixtureManifest
)

# Runner seguro para PowerShell 5.1.
# No imprime cuerpos HTTP, credenciales, cookies ni tokens.
# Las mutaciones están deliberadamente bloqueadas hasta contar con un plan
# de fixtures y limpieza aprobado fuera de este runner.

$ErrorActionPreference = 'Stop'
$script:BaseUri = [Uri]$BaseUrl.TrimEnd('/')
$script:Session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$script:Results = New-Object System.Collections.Generic.List[object]

function Add-Result {
    param(
        [string]$Case,
        [string]$Status,
        [string]$Evidence
    )

    $script:Results.Add([pscustomobject]@{
        Case = $Case
        Status = $Status
        Evidence = $Evidence
    })
    Write-Output ("{0}={1}" -f $Case, $Status)
}

function Test-TcpPort {
    param(
        [string]$HostName,
        [int]$Port
    )

    $client = New-Object System.Net.Sockets.TcpClient
    try {
        $async = $client.BeginConnect($HostName, $Port, $null, $null)
        if (-not $async.AsyncWaitHandle.WaitOne(($script:TimeoutSec * 1000), $false)) {
            return $false
        }
        $client.EndConnect($async)
        return $true
    } catch {
        return $false
    } finally {
        $client.Close()
    }
}

function Invoke-SafeRequest {
    param(
        [ValidateSet('GET', 'POST')]
        [string]$Method,
        [string]$Path,
        [hashtable]$Headers,
        [string]$Body,
        [string]$ContentType = 'application/json',
        [Microsoft.PowerShell.Commands.WebRequestSession]$WebSession = $script:Session
    )

    $uri = $script:BaseUri.AbsoluteUri.TrimEnd('/') + '/' + $Path.TrimStart('/')
    try {
        $params = @{
            Uri = $uri
            Method = $Method
            UseBasicParsing = $true
            TimeoutSec = $script:TimeoutSec
            WebSession = $WebSession
            ErrorAction = 'Stop'
        }
        if ($Headers) { $params.Headers = $Headers }
        if ($PSBoundParameters.ContainsKey('Body')) {
            $params.Body = $Body
            $params.ContentType = $ContentType
        }
        $response = Invoke-WebRequest @params
        return [pscustomobject]@{
            StatusCode = [int]$response.StatusCode
            Headers = $response.Headers
            Body = [string]$response.Content
            Error = $null
        }
    } catch {
        $statusCode = 0
        $response = $_.Exception.Response
        if ($response -and $response.StatusCode) {
            $statusCode = [int]$response.StatusCode.value__
        }
        return [pscustomobject]@{
            StatusCode = $statusCode
            Headers = $null
            Body = ''
            Error = $_.Exception.Message
        }
    }
}

function Test-UnauthenticatedContract {
    $safeGets = @(
        @{ Name = 'UNAUTH_NOTE_VIEW'; Path = 'clinica/nota-subsecuente/paciente/0/referencia/invalid' },
        @{ Name = 'UNAUTH_NOTE_DETAIL'; Path = 'buscar/nota-subsecuente/0' },
        @{ Name = 'UNAUTH_LAB_DETAIL'; Path = 'buscar/laboratorio/0' }
    )

    foreach ($case in $safeGets) {
        $result = Invoke-SafeRequest -Method GET -Path $case.Path -WebSession (New-Object Microsoft.PowerShell.Commands.WebRequestSession)
        if ($result.StatusCode -in 401, 403, 404, 302, 303, 307, 308) {
            Add-Result -Case $case.Name -Status 'PASS_RUNTIME' -Evidence ("HTTP_" + $result.StatusCode)
        } elseif ($result.StatusCode -eq 0) {
            Add-Result -Case $case.Name -Status 'NOT_EXECUTED' -Evidence 'HTTP_UNAVAILABLE'
        } else {
            Add-Result -Case $case.Name -Status 'REVIEW_REQUIRED' -Evidence ("HTTP_" + $result.StatusCode)
        }
    }

    $emptyPost = Invoke-SafeRequest -Method POST -Path 'clinica/paciente/insert-nota-subsecuente' -Body '{}'
    if ($emptyPost.StatusCode -in 401, 403, 404, 302, 303, 307, 308, 422) {
        Add-Result -Case 'UNAUTH_NOTE_POST_NO_TOKEN' -Status 'PASS_RUNTIME' -Evidence ("HTTP_" + $emptyPost.StatusCode)
    } elseif ($emptyPost.StatusCode -eq 0) {
        Add-Result -Case 'UNAUTH_NOTE_POST_NO_TOKEN' -Status 'NOT_EXECUTED' -Evidence 'HTTP_UNAVAILABLE'
    } else {
        Add-Result -Case 'UNAUTH_NOTE_POST_NO_TOKEN' -Status 'REVIEW_REQUIRED' -Evidence ("HTTP_" + $emptyPost.StatusCode)
    }
}

function Invoke-DoctorLogin {
    param(
        [PSCredential]$Credential
    )

    if (-not $Credential) {
        $Credential = Get-Credential -Message 'Credenciales del Doctor de prueba. No se guardan ni se imprimen.'
    }

    $password = $Credential.GetNetworkCredential().Password
    $body = (@{
        usuario = $Credential.UserName
        password = $password
    } | ConvertTo-Json -Compress)

    $result = Invoke-SafeRequest -Method POST -Path 'clinica/login' -Body $body
    $password = $null

    if ($result.StatusCode -ne 200) {
        throw 'El login de prueba no fue autorizado; no se continúa con lecturas autenticadas.'
    }

    $cookies = $script:Session.Cookies.GetCookies($script:BaseUri)
    $jwt = $cookies | Where-Object { $_.Name -eq 'CLINICAJWT' }
    if (-not $jwt) {
        throw 'El login no dejó una cookie de sesión utilizable en la sesión de PowerShell.'
    }

    Add-Result -Case 'DOCTOR_LOGIN' -Status 'PASS_RUNTIME' -Evidence 'SESSION_COOKIE_PRESENT'
}

function Invoke-AuthenticatedReadOnlyValidation {
    if ($PatientId -lt 1 -or [string]::IsNullOrWhiteSpace($Reference)) {
        throw 'La validación autenticada requiere -PatientId y -Reference.'
    }

    $referencePath = [Uri]::EscapeDataString($Reference)
    $cases = @(
        @{ Name = 'AUTH_NOTE_VIEW'; Path = ('clinica/nota-subsecuente/paciente/{0}/referencia/{1}' -f $PatientId, $referencePath) },
        @{ Name = 'AUTH_NOTE_HISTORY'; Path = ('buscar/tabla-notas-subsecuentes/{0}/{1}' -f $PatientId, $referencePath) },
        @{ Name = 'AUTH_LAB_HISTORY'; Path = ('buscar/tabla-laboratorio/{0}/{1}' -f $PatientId, $referencePath) }
    )

    if ($NoteId -gt 0) {
        $cases += @{ Name = 'AUTH_NOTE_DETAIL'; Path = ('buscar/nota-subsecuente/{0}' -f $NoteId) }
    }
    if ($LaboratoryId -gt 0) {
        $cases += @{ Name = 'AUTH_LAB_DETAIL'; Path = ('buscar/laboratorio/{0}' -f $LaboratoryId) }
    }

    foreach ($case in $cases) {
        $result = Invoke-SafeRequest -Method GET -Path $case.Path
        if ($result.StatusCode -ge 200 -and $result.StatusCode -lt 300) {
            Add-Result -Case $case.Name -Status 'PASS_RUNTIME' -Evidence ("HTTP_" + $result.StatusCode)
        } elseif ($result.StatusCode -eq 0) {
            Add-Result -Case $case.Name -Status 'NOT_EXECUTED' -Evidence 'HTTP_UNAVAILABLE'
        } else {
            Add-Result -Case $case.Name -Status 'REVIEW_REQUIRED' -Evidence ("HTTP_" + $result.StatusCode)
        }
    }
}

Write-Output 'TH_DOCTOR_SUBSEQUENT_NOTE_SECURITY_HTTP_VALIDATION_04B_RUNNER'
Write-Output ('BASE_URL=' + $script:BaseUri.AbsoluteUri.TrimEnd('/'))
Write-Output ('POWERSHELL_VERSION=' + $PSVersionTable.PSVersion.ToString())
Write-Output ('PROJECT_ROOT=' + (Get-Location).Path)

if (-not (Test-TcpPort -HostName $script:BaseUri.DnsSafeHost -Port $script:BaseUri.Port)) {
    Add-Result -Case 'LOCAL_HTTP_PREFLIGHT' -Status 'BLOCKED' -Evidence 'TCP_UNAVAILABLE'
    Write-Output 'BLOCK_REASON=Servidor local no accesible. No se ejecutan pruebas HTTP ni mutaciones.'
    exit 2
}
Add-Result -Case 'LOCAL_HTTP_PREFLIGHT' -Status 'PASS_RUNTIME' -Evidence 'TCP_REACHABLE'

if (-not (Test-TcpPort -HostName '127.0.0.1' -Port 3306)) {
    Add-Result -Case 'LOCAL_MYSQL_PREFLIGHT' -Status 'BLOCKED' -Evidence 'TCP_UNAVAILABLE'
    Write-Output 'BLOCK_REASON=MySQL local no accesible. No se ejecutan pruebas mutantes.'
    exit 2
}
Add-Result -Case 'LOCAL_MYSQL_PREFLIGHT' -Status 'PASS_RUNTIME' -Evidence 'TCP_REACHABLE'

$root = Invoke-SafeRequest -Method GET -Path '/'
if ($root.StatusCode -eq 0) {
    Add-Result -Case 'LOCAL_HTTP_ROOT' -Status 'BLOCKED' -Evidence 'HTTP_UNAVAILABLE'
    Write-Output 'BLOCK_REASON=No se pudo verificar el proyecto local por HTTP.'
    exit 2
}
Add-Result -Case 'LOCAL_HTTP_ROOT' -Status 'PASS_RUNTIME' -Evidence ("HTTP_" + $root.StatusCode)

Test-UnauthenticatedContract

if ($AllowSyntheticMutations) {
    Add-Result -Case 'SYNTHETIC_MUTATIONS' -Status 'BLOCKED' -Evidence 'RUNNER_REQUIRES_APPROVED_FIXTURE_HARNESS'
    Write-Output 'BLOCK_REASON=Las mutaciones no están implementadas en este runner. Requieren fixture, limpieza y aprobación explícita.'
    exit 3
}

if ($InteractiveDoctorLogin -or $RunAuthenticatedReadOnly) {
    try {
        Invoke-DoctorLogin -Credential $null
        if ($RunAuthenticatedReadOnly) {
            Invoke-AuthenticatedReadOnlyValidation
        }
    } catch {
        Add-Result -Case 'AUTHENTICATED_READ_ONLY' -Status 'BLOCKED' -Evidence 'LEGITIMATE_SESSION_NOT_ESTABLISHED'
        Write-Output ('BLOCK_REASON=' + $_.Exception.Message)
        exit 4
    }
} else {
    Add-Result -Case 'AUTHENTICATED_READ_ONLY' -Status 'NOT_EXECUTED' -Evidence 'USE_INTERACTIVE_LOGIN_AND_FIXTURE_IDS'
}

Add-Result -Case 'DATABASE_NAME_VERIFICATION' -Status 'NOT_VERIFIED' -Evidence 'REQUIRES_NATIVE_LOCAL_PREFLIGHT'
Add-Result -Case 'FIXTURE_ISOLATION' -Status 'NOT_VERIFIED' -Evidence 'NO_MUTATIONS_PERFORMED'
Add-Result -Case 'BACKEND_IDEMPOTENCY' -Status 'NOT_VERIFIED' -Evidence 'NO_MUTATIONS_PERFORMED'
Write-Output 'DATABASE_WRITES=NONE'
Write-Output 'TOKENS_CREDENTIALS_LOGGED=NO'
exit 0
