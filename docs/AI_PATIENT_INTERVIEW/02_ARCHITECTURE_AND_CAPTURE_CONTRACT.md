# Arquitectura y contrato inicial de captura

Estado: **APROBADO para el piloto M03/Tabaquismo; no es contrato de producción**. La aprobación está limitada a la autorización adjunta y a pruebas sintéticas.

## 1. Fuente única de verdad

Las dos modalidades deben leer y escribir las mismas respuestas M03:

`pac_respuestas_paciente_modulo_3(id, id_pregunta, id_paciente, respuesta)`.

El catálogo sigue siendo la fuente de preguntas y tipos; no se deben copiar preguntas a una tabla de entrevista ni crear una historia paralela.

## 2. Flujo propuesto

1. Paciente autenticado abre M03.
2. El servidor valida sesión y pertenencia del paciente.
3. El selector permite elegir formulario tradicional o Entrevista Guiada.
4. La interfaz guiada solicita al servidor el tema/pregunta actual desde el catálogo.
5. Se muestra una sola pregunta y el valor previamente persistido, si existe.
6. El paciente escribe una respuesta.
7. La interfaz muestra una confirmación legible: pregunta, respuesta interpretada y consecuencia si aplica.
8. Solo tras confirmación se llama al adaptador de guardado existente.
9. El servidor vuelve a validar paciente, respuesta, tema, tipo y CSRF.
10. El servidor actualiza la respuesta existente; no inserta duplicados.
11. La siguiente pregunta se carga desde el catálogo y el avance se puede reanudar.

## 3. Contratos mínimos

### Lectura

La lectura debe identificar tema, pregunta, tipo, referencia de respuesta y valor persistido. Debe distinguir `null`/vacío de `No`, `Sí`, texto y número cero. No debe incluir IDs de catálogo como contenido clínico visible.

### Validación

- `idPaciente`, `idTema` e `idRespuesta`: enteros positivos.
- `idTipo`: solo el tipo obtenido del catálogo, no confiado al navegador.
- `detalle`: presente; validación específica por tipo.
- `No` y `Sí`: valores explícitos para select.
- Texto: longitud y normalización según las reglas actuales.
- Cero numérico: valor válido cuando el tipo y la pregunta lo permitan.
- Preguntas condicionales: solo avanzar o limpiar respuestas conforme a la regla existente.

### Persistencia

El adaptador debe llamar a `AntecedentesNoPatologicosModel::editarCuestionarioModulo` o a una extracción mínima de esa lógica aprobada. No debe duplicar la limpieza de respuestas ni usar `INSERT` si ya existe una respuesta para la pregunta.

Antes de persistir, debe existir CSRF validado en servidor y una comprobación de que la respuesta pertenece al paciente y tema de la sesión.

### Reanudación

El avance de navegación puede permanecer local porque no es dato clínico; las respuestas deben recuperarse siempre desde servidor. Si no existe un borrador seguro, el MVP debe reanudar desde la última respuesta persistida, no desde una transcripción local.

## 4. Sobrescritura y confirmación

Una respuesta previamente registrada debe mostrarse como valor existente. El cambio requiere una confirmación explícita. No se debe sobrescribir de forma silenciosa por una inferencia de IA. El historial actual no aporta una marca de “capturada por entrevista”; no crearla en esta fase.

## 5. Preguntas condicionales

La regla observada en M03 es que una respuesta `No` en el select principal puede limpiar el resto del tema. La interfaz debe explicar esa consecuencia y pedir confirmación. Las preguntas dependientes no se deben clasificar como `No aplica` por ausencia de respuesta.

## 6. Autorización y privacidad

La sesión debe resolverse en servidor con `AuthMiddleware` y `assertPatientAccess`. El navegador no puede elegir un paciente distinto mediante un campo manipulable. El piloto será determinista y local; no enviará contexto clínico a Gemini ni a otro proveedor. El alcance obligatorio es M03/Tabaquismo (tema 1, preguntas 1–5); Ejercicio (tema 3) queda diferido.

No usar `localStorage` para respuestas, texto clínico, audio o transcripciones. No registrar payloads clínicos. Escapar toda salida HTML.

## 7. Reutilización y propuestas

**Verificado y reutilizable:** catálogo M03, consulta de respuestas, navegación existente, `speechSynthesis` para lectura, autorización de paciente, modelo de actualización transaccional para select `No`.

**Requiere adaptador:** lectura estructurada para la interfaz guiada, selector de modalidad, confirmación previa, CSRF del endpoint, manejo uniforme de errores y estado de respuesta previa.

**No necesario para el MVP:** proveedor Gemini, transcripción de voz, tablas nuevas, motor de recomendaciones, trazabilidad de origen IA.

## 8. Definición de no duplicación

La misma combinación `id_paciente + id_pregunta` debe resolverse a una sola respuesta editable. El adaptador no insertará una segunda fila. La prueba sintética debe comparar conteo y valor antes/después y verificar que no se alteren otros temas o módulos.

## 9. Decisiones pendientes

- Ejecutar E2E autenticado en base sintética aislada y validar visualmente el navegador.
- Aprobar el contrato JSON/HTML del adaptador.
- Resolver CSRF P0.
- Definir si una respuesta previamente registrada puede editarse directamente o exige confirmación adicional.
- Definir el texto institucional de “Entrevista Guiada”.

## 10. Corrección arquitectónica Fase 3.4

La experiencia conversacional se separa del formulario M03. El portal Paciente ofrece `/historia-clinica/entrevista` y el formulario tradicional conserva `/historia-clinica/antecedentes-personales-no-patologicos/paciente/{idPaciente}` sin selector conversacional incrustado. Ambas rutas leen el catálogo M03 y comparten `pac_respuestas_paciente_modulo_3`; la captura independiente valida la misma sesión, paciente, CSRF y concurrencia antes de delegar al modelo existente. El piloto continúa determinista y limitado a Tabaquismo.
