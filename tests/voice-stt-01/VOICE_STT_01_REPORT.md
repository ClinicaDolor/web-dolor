# VOICE-STT-01 — Benchmark aislado de faster-whisper

Fecha: 2026-10-13.

## Alcance

Benchmark local y offline del modelo `Systran/faster-whisper-small` con tres
audios generados por Windows SAPI. El contenido es ficticio y sólo cubre
tabaquismo, medicamentos, dosis, números y negaciones. No se usó micrófono,
no se cargó ningún expediente y no se creó endpoint, servicio persistente ni
integración PHP.

## Entorno reproducible

- Python: 3.13.15, dentro de `tests/voice-stt-01/.venv`.
- faster-whisper: 1.2.1.
- CTranslate2: 4.8.3.
- PyAV: 18.1.0.
- CPU INT8, hasta 8 hilos.
- Modelo: `Systran/faster-whisper-small`, snapshot
  `536b0662742c02347bc0e980a01041f333bce120`.
- Peso local: 483,546,902 bytes (`model.bin`), más tokenizer y configuración.
- Licencia declarada por el model card: MIT.
- El benchmark se ejecutó con `HF_HUB_OFFLINE=1`; después de descargar el
  snapshot no realizó solicitudes externas.

La primera combinación PyAV 19.0.1 no fue compatible con faster-whisper 1.2.1:
la transcripción falló porque PyAV eliminó `metadata_errors`. Se fijó PyAV
18.1.0 únicamente en el venv aislado. La incompatibilidad está documentada en
el issue del proyecto faster-whisper.

## Mediciones

| Audio | Duración | Transcripción warm | Factor tiempo real | Resultado |
|---|---:|---:|---:|---|
| 03 s | 3.144 s | 4.402 s | 1.400 | Correcta |
| 10 s | 8.848 s | 5.610 s | 0.634 | Correcta |
| 20 s | 18.361 s | 7.156 s | 0.390 | Error de medicamento |

Inicialización del modelo desde snapshot local: 2.051 s. RAM máxima del
proceso: 588.9 MB. CPU máxima agregada: 795.2% con hasta 8 hilos; representa
uso paralelo de varios núcleos, no 795.2% de un solo núcleo.

## Fidelidad clínica sintética

- Negaciones: PASS en los tres audios (`No fumo`).
- Números y dosis: PASS; `400` y `500` fueron reconocidos correctamente.
- Ibuprofeno: PASS.
- Paracetamol: FAIL en el audio de 20 s; se transcribió como `peraseta mal`.
- Error clínicamente significativo potencial: sí, porque un nombre de
  medicamento quedó alterado.

La transcripción debe permanecer editable y nunca convertirse directamente en
un guardado clínico. Con estos datos, `small` demuestra viabilidad técnica
offline, pero no justifica todavía una activación clínica sin revisión humana.

## Estado

```yaml
PHASE: VOICE-STT-01
PYTHON_COMPATIBILITY: PASS_PYTHON_3_13
FASTER_WHISPER_VERSION: 1.2.1
CTRANSLATE2_VERSION: 4.8.3
PYAV_VERSION: 18.1.0
MODEL: Systran/faster-whisper-small
MODEL_PROVENANCE: "Hugging Face snapshot 536b0662742c02347bc0e980a01041f333bce120"
MODEL_LICENSE: MIT
MODEL_STORAGE: 483546902_BYTES_MODEL_BIN_PLUS_METADATA
CPU_INT8: PASS

AUDIO_3S_LATENCY: 4.402s
AUDIO_10S_LATENCY: 5.610s
AUDIO_20S_LATENCY: 7.156s
COLD_START: 2.051s_LOCAL_SNAPSHOT
WARM_TRANSCRIPTION: PASS_WITH_ONE_MEDICATION_ERROR
PEAK_RAM: 588.9MB
CPU_USAGE: 795.2_PERCENT_AGGREGATE_MAX_8_THREADS

SPANISH_TRANSCRIPTION_ACCURACY: PARTIAL_2_OF_3_CRITICAL_CASES
MEDICATION_NAMES: "Ibuprofeno PASS; paracetamol FAIL in 20s sample"
DOSES_AND_UNITS: PASS
NUMBERS: PASS
NEGATIONS: PASS
CLINICALLY_SIGNIFICANT_ERRORS: "Paracetamol transcrito como peraseta mal"

OFFLINE_TRANSCRIPTION: PASS
EXTERNAL_AUDIO_TRANSMISSION: NO
PERSISTENT_SERVICE_CREATED: NO
HTTP_ENDPOINT_CREATED: NO

BD_CLINICA_MODIFIED: NO
PATIENT_310_MODIFIED: NO
LEGACY_FORMS_MODIFIED: NO
ENV_MODIFIED: NO

FILES_CREATED: "tests/voice-stt-01/.venv; requirements.lock.txt; generate-synthetic-audio.ps1; download-model.py; benchmark.py; audio/*.wav; audio/manifest.json; model-cache; model-cache/model-manifest.json; benchmark-results.json"
DEPENDENCIES_INSTALLED: "venv only; no global Python changes"
RESULT: "TECHNICALLY_VIABLE_OFFLINE_WITH_HUMAN_EDIT; NOT_CLINICALLY_READY_FOR_AUTOMATIC_USE"
NEXT_RECOMMENDATION: "Before integration, benchmark a larger multilingual model or improve the review policy with synthetic medication vocabulary; do not install another model automatically"
```
