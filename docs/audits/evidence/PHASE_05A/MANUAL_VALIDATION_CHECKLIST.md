# PHASE-05A-R1 — Checklist manual de evidencia visual

## Reglas de seguridad

- [ ] Confirmé que el host apunta al entorno sintético y no a `bd_clinica`.
- [ ] Uso únicamente la cuenta/paciente sintético autorizado.
- [ ] Abrí Chrome o Edge con un perfil temporal aislado; no usé el perfil personal.
- [ ] No copié cookies, contraseñas, PIN ni tokens a archivos o conversaciones.
- [ ] No ejecuté POST clínicos, descargas, PDF ni finalizaciones irreversibles fuera del fixture autorizado.

## Preparación del navegador

1. Abrir `clinica.test` sólo si el responsable del entorno ya confirmó que resuelve al servidor sintético. Si no existe ese host, utilizar la URL local temporal indicada por el procedimiento del fixture; no editar el archivo `hosts` en esta fase.
2. Iniciar sesión mediante el formulario real con la cuenta sintética autorizada.
3. Abrir DevTools (`F12`) y mantener visibles las pestañas **Console** y **Network**.
4. Activar el modo responsive de DevTools (`Ctrl+Shift+M`).
5. Registrar en cada captura el viewport efectivo mostrado por DevTools y, cuando sea posible, confirmar en Console:

   ```js
   ({ width: window.innerWidth, height: window.innerHeight })
   ```

6. Guardar PNG en esta carpeta, con nombres sin datos personales:
   `docs/audits/evidence/PHASE_05A/`

## Compuerta mínima

| Prueba | PASS | FAIL | NO VERIFICADO | Evidencia / nombre PNG |
|---|---:|---:|---:|---|
| Sesión sintética autenticada | [ ] | [ ] | [ ] | |
| P04 visible | [ ] | [ ] | [ ] | |
| Viewport efectivo 375×667 | [ ] | [ ] | [ ] | |
| PNG real legible | [ ] | [ ] | [ ] | |
| Console accesible | [ ] | [ ] | [ ] | |
| Network accesible | [ ] | [ ] | [ ] | |
| Sin información clínica real | [ ] | [ ] | [ ] | |

**Regla:** si una fila esencial no es PASS, detener la matriz y registrar `BLOCKED`.

## Primera muestra obligatoria

Ejecutar primero en `375×667` y `1280×720`:

| Vista | Render | Modal | Console | Network | Overflow | PNG móvil | PNG escritorio | Notas |
|---|---|---|---|---|---|---|---|---|
| P04 — Antecedentes familiares | | | | | | | | |
| P07 — Antecedentes personales patológicos | | | | | | | | |
| P10 — Procedimientos para controlar el dolor | | | | | | | | |
| P11 — Evaluación del dolor / mapa corporal | | | | | | | | |

### Revisión funcional/visual de la muestra

- [ ] P04: modal canónico, controles Sí/No, una pregunta visible, navegación y footer.
- [ ] P07: modal y contenido sin overflow vertical/horizontal en móvil.
- [ ] P10: modal centrado, botones y estado de error de red comprensible.
- [ ] P11: mapa corporal visible, escalable y sin pérdida de zonas/eventos.
- [ ] Sidebar: sólo una opción activa y cierre/expansión correctos.
- [ ] Narración: el resaltado amarillo corresponde al texto activo.
- [ ] Audio real: sólo marcar PASS tras comprobación audible humana.

## Matriz completa posterior a la compuerta

Viewports requeridos: `1440×900`, `1280×720`, `1024×768`, `768×1024`, `430×932`, `375×667`.

| Vista | 1440×900 | 1280×720 | 1024×768 | 768×1024 | 430×932 | 375×667 | PNG y notas |
|---|---|---|---|---|---|---|---|
| P01 | | | | | | | |
| P02 | | | | | | | |
| P03 | | | | | | | |
| P04 | | | | | | | |
| P05 | | | | | | | |
| P06 | | | | | | | |
| P07 | | | | | | | |
| P08 | | | | | | | |
| P09 | | | | | | | |
| P10 | | | | | | | |
| P11 | | | | | | | |

En cada celda usar solamente `PASS`, `FAIL` o `NO VERIFICADO`. Un botón presente en el DOM, una captura sin viewport registrado o una prueba estática no equivalen a PASS funcional.

## Checklist por pantalla

- [ ] Render y título correctos.
- [ ] Sin overflow horizontal.
- [ ] Tarjetas, botones y controles legibles.
- [ ] Sidebar sin estados activos duplicados.
- [ ] Modal centrado y cerrable con Escape cuando aplique.
- [ ] Console sin errores relevantes.
- [ ] Network sin respuestas inesperadas.
- [ ] Navegación original conserva destino.
- [ ] Captura full-page PNG guardada.
- [ ] Datos observados exclusivamente sintéticos.
