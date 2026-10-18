# Aceptación offline P01–P03

Este directorio contiene una representación estática y local de las tres vistas
del Portal Paciente. Sirve únicamente para inspeccionar estructura, foco,
responsive y estados básicos sin iniciar PHP, HTTP, sesión, PDO, SQL ni base de
datos.

## Uso

Abrir `index.html` directamente en un navegador local. La compuerta visual de
esta actividad queda pendiente hasta que una persona ejecute la revisión en los
viewports requeridos y conserve capturas y resultados de consola.

## Límites deliberados

- P01 sólo reproduce la validación cliente del PIN vacío. No autentica, no
  contiene un formulario enviable y no envía credenciales.
- P02 usa estados y datos ficticios para representar la composición del
  dashboard. No calcula porcentajes ni consulta el catálogo real.
- P03 reproduce los cuatro bloques de datos como lectura. La vista PHP actual
  no contiene controles de edición; el botón de finalización está representado
  como no ejecutable en este harness.
- La navegación entre pantallas usa botones y un único documento; no modifica la
  URL ni intenta navegar otros frames. No hay `fetch`, XHR, WebSocket,
  imágenes remotas, fuentes remotas ni formularios con destino.

## Resultado esperado

Registrar PASS, FAIL o NO VERIFICADO para cada viewport y conservar cualquier
captura fuera de este repositorio si el procedimiento local del responsable lo
requiere. Este harness no constituye evidencia de autenticación, autorización,
persistencia ni funcionamiento integrado.
