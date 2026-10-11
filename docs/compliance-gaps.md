# Brechas normativas y de cumplimiento

Este documento no declara cumplimiento. Sólo separa evidencia técnica observable de requisitos que requieren validación jurídica, clínica, de privacidad y de operación.

## NOM-004-SSA3 — Expediente clínico

### Evidencia técnica

- Existen datos de identificación, antecedentes, medicación, evaluación del dolor, recetas, notas y laboratorios en las vistas y modelos bajo app/views, app/Models y app/Controllers.
- Existen timestamps en varias tablas observadas y una acción de finalización de módulos.

### No demostrado

- Firma autógrafa o electrónica, autoría inequívoca, sello de tiempo y corrección por addenda.
- Integridad, inmutabilidad y versionado de notas ya firmadas.
- Catálogo formal de contenidos mínimos por tipo de nota.
- Control de acceso, bitácora de lectura y conservación conforme a política aprobada.
- Procedimiento de entrega, corrección, exportación y resguardo del expediente.

Conclusión: no es posible afirmar cumplimiento NOM-004 desde el código.

## NOM-024-SSA3 — Sistemas de información de registro electrónico para la salud

### Evidencia técnica

- El sistema usa PHP, MySQL, PDO y JWT; las rutas y tablas son propietarias.
- No se encontró API de interoperabilidad, esquema versionado, terminologías, perfiles de intercambio o pruebas de conformidad en el repositorio.

### Pendiente de validar

- Si el alcance regulatorio aplica al producto y a su modalidad de uso.
- Interoperabilidad, seguridad, disponibilidad, trazabilidad, respaldo y recuperación exigibles.
- Identificadores, catálogos clínicos y controles de calidad del dato.

Conclusión: no hay evidencia suficiente para declarar conformidad NOM-024.

## LFPDPPP — Protección de datos personales

### Evidencia técnica

- El sitio público muestra un enlace a Aviso de Privacidad en app/views/components/web-ubicacion-contacto.php.
- Las cookies JWT incluyen HttpOnly, SameSite=Lax y Secure cuando aplica HTTPS.
- Los datos de configuración se cargan por variables de entorno y no se reproducen en esta documentación.

### No demostrado

- Contenido, vigencia y publicación verificable del aviso integral y simplificado.
- Consentimiento, finalidades, transferencias, derechos ARCO, revocación y atención de solicitudes.
- Cifrado en reposo, control de respaldos, minimización, retención y eliminación segura.
- Registro de accesos, incidentes y responsables del tratamiento.

Conclusión: el enlace al aviso y los atributos de cookie no prueban cumplimiento LFPDPPP.

## Criterio de liberación

La entrega con información clínica real debe permanecer bloqueada hasta que el responsable jurídico, clínico y de seguridad apruebe las brechas anteriores y exista evidencia de pruebas de aceptación, respaldo/restauración y trazabilidad.
