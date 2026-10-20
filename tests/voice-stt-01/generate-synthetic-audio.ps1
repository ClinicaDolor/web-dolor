[CmdletBinding()]
param(
    [string]$OutputDirectory = (Join-Path $PSScriptRoot 'audio')
)

$ErrorActionPreference = 'Stop'
New-Item -ItemType Directory -Force -Path $OutputDirectory | Out-Null
Add-Type -AssemblyName System.Speech

$synth = New-Object System.Speech.Synthesis.SpeechSynthesizer
$voice = $synth.GetInstalledVoices() |
    Where-Object { $_.VoiceInfo.Culture.Name -like 'es-*' } |
    Sort-Object @{Expression={ if ($_.VoiceInfo.Name -like '*Pablo*') { 0 } else { 1 } }}, VoiceInfo.Name |
    Select-Object -First 1
if (-not $voice) { throw 'No hay una voz local es-* disponible para generar audio ficticio.' }
$selectedVoice = $false
try {
    $synth.SelectVoice($voice.VoiceInfo.Name)
    $selectedVoice = $true
} catch {
    Write-Warning "Windows no permitió seleccionar explícitamente la voz es-*; se usará la voz predeterminada local."
}
$effectiveVoiceName = $synth.Voice.Name
$synth.Rate = 0
$synth.Volume = 100

$samples = @(
    [pscustomobject]@{
        id = 'audio-03s'
        targetSeconds = 3
        text = 'No fumo y no tomo medicamentos.'
        pause = '0.5s'
    },
    [pscustomobject]@{
        id = 'audio-10s'
        targetSeconds = 10
        text = 'No fumo ni bebo alcohol. Tomo ibuprofeno de cuatrocientos miligramos dos veces al día para el dolor.'
        pause = '2s'
    },
    [pscustomobject]@{
        id = 'audio-20s'
        targetSeconds = 20
        text = 'No fumo ni bebo alcohol. Actualmente tomo ibuprofeno de cuatrocientos miligramos dos veces al día y paracetamol de quinientos miligramos por la noche. La dosis me ayuda un poco, pero el dolor continúa y no he tenido efectos secundarios.'
        pause = '3s'
    }
)

function Escape-Ssml([string]$value) {
    return [System.Security.SecurityElement]::Escape($value)
}

$manifest = @()
foreach ($sample in $samples) {
    $path = Join-Path $OutputDirectory ($sample.id + '.wav')
    $synth.SetOutputToWaveFile($path)
    $voicePrefix = if ($selectedVoice) { '<voice name="' + (Escape-Ssml $effectiveVoiceName) + '">' } else { '' }
    $voiceSuffix = if ($selectedVoice) { '</voice>' } else { '' }
    $ssml = '<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xml:lang="es-ES">' + $voicePrefix + (Escape-Ssml $sample.text) + '<break time="' + $sample.pause + '"/>' + $voiceSuffix + '</speak>'
    $synth.SpeakSsml($ssml)
    $synth.SetOutputToNull()
    $manifest += [pscustomobject]@{
        id = $sample.id
        file = $path
        target_seconds = $sample.targetSeconds
        expected_text = $sample.text
        requested_voice = $voice.VoiceInfo.Name
        effective_voice = $effectiveVoiceName
        voice_selection = if ($selectedVoice) { 'requested_es_voice' } else { 'system_default_voice' }
        synthetic_only = $true
    }
}
$synth.Dispose()
$manifest | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath (Join-Path $OutputDirectory 'manifest.json') -Encoding utf8
Write-Output (ConvertTo-Json $manifest -Depth 4)
