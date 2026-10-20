# P11 — aceptación offline del mapa del dolor

Este directorio contiene una prueba local y desconectada del mapa corporal de P11. No es la aplicación clínica ni una sesión de paciente.

## Abrir en Chrome

1. Abrir `index.html` directamente en Google Chrome desde el Explorador de archivos.
2. Confirmar que la franja `PRUEBA OFFLINE P11` aparece y que no se ha abierto `clinica.test`.
3. Abrir DevTools (`F12`) y revisar Console y Network. No debe haber solicitudes de red ni formularios enviados; la política CSP bloquea conexiones salientes.
4. Seleccionar un color y dibujar sobre el mapa de frente.
5. Cambiar a `Imagen de espalda`, repetir la selección y volver a la vista anterior.
6. Probar los tamaños 375×667, 430×932, 768×1024, 1024×768 y 1440×900 con el modo responsive de DevTools.
7. Probar la navegación por teclado: tabular entre radios y botones, seleccionar un radio con la barra espaciadora y activar los botones con Enter.
8. Cerrar la pestaña sin guardar. Las marcas son temporales y no representan persistencia clínica.

## Qué reutiliza y qué no

- Reutiliza `public/assets/css/app.css` y la estructura/clases de P11.
- Reutiliza la lógica real de `initMap`: coordenadas escaladas desde `getBoundingClientRect()`, selección de color, `pointerdown`/`pointermove`/`pointerup`, captura de puntero y dibujo sobre canvas.
- Usa únicamente los recursos gráficos genéricos `Frente_M.png` y `Espalda_M.png`.
- Esos PNG contienen las palabras laterales apiladas verticalmente. El harness las cubre sólo en sus márgenes con capas blancas no interactivas y muestra `Derecha`/`Izquierda` horizontalmente; esto no modifica el PNG, el canvas de 1200×1800 ni el cálculo de coordenadas.
- El adaptador local sustituye las cargas AJAX por paneles HTML estáticos y sustituye el guardado por un mensaje explícito de “no se guardó”. Los botones de limpiar están deshabilitados porque esa acción requiere backend.
- No prueba autorización, persistencia, finalización, recuperación de imágenes, privacidad ni audio de la aplicación real.

## Clasificación de evidencia

- `REAL_CLIENT_CODE_OFFLINE`: interacción de selección/dibujo basada en el manejador real y recursos locales genéricos.
- `STATIC_ONLY`: estructura HTML, CSS, estados accesibles y contratos de navegación.
- `BACKEND_DEPENDENT`: limpiar, guardar, recuperar, autorización y finalización.
- `NOT_REPRODUCIBLE`: comportamiento dentro de una sesión clínica real hasta contar con un entorno autorizado.

La prueba manual del propietario queda pendiente. No introducir pacientes, credenciales, contraseñas, SQL ni datos clínicos.
