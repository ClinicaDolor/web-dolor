# Instrucciones locales del proyecto

## Asistente conversacional M06/M07

Después de modificar código del asistente conversacional, ejecutar desde el host local Windows la suite reutilizable:

```text
C:\xampp\php\php.exe tests/patient-interview/run-local-conversational-tests.php conversational
```

El runner realiza un preflight estrictamente de solo lectura, fijado a `clinica_dolor_demo_20261010_final`, y después las regresiones M06/M07 estáticas y simuladas incluidas en su allowlist. No ejecutar en automático pruebas MySQL con escrituras, navegador, Gemini ni STT.

El modo `preflight` sólo verifica PHP/PDO, base efectiva, esquema y fixtures autorizados. El modo `conversational`, una vez superado ese preflight, también inicia temporalmente el Apache sintético loopback existente y realiza únicamente GET anónimos de acceso; cada respuesta debe mostrar `X-M07-Database-Guard: PASS`, la entrevista debe redirigir a acceso sin sesión y el runner debe verificar la detención del proceso, trabajadores y puerto. No envía cookies ni POST y debe limpiar el Apache en `finally`.

Estos dos modos pueden registrarse como acciones locales del proyecto en Settings > Local environments de Codex. No crear un formato `.codex` por suposición: si el editor de acciones no está disponible, el agente puede ejecutar los modos desde su runner local cuando tenga autorización de sandbox.

Si el sandbox impide el acceso local necesario para el preflight o para Apache aislado, solicitar únicamente autorización específica para el runner contra la base sintética aprobada y loopback; no ampliar permisos ni apuntar a `bd_clinica`.

Mantener `M07CanonicalActivationGate` cerrado. La integración M07 con escrituras solo puede ejecutarse en un entorno sintético aislado tras autorización y aceptación explícitas; no automatizar su activación. No usar ni mostrar datos de pacientes reales.
