<?php

declare(strict_types=1);

/**
 * Contrato estático de la pantalla independiente Nueva nota subsecuente.
 * No inicia la aplicación, no abre PDO y no envía HTTP.
 */

$root = dirname(__DIR__, 2);
$read = static function (string $relative) use ($root): string {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('No se pudo leer ' . $relative);
    }
    return $contents;
};

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$view = $read('app/views/clinica/pacientes-nota-subsecuente-nueva.php');
$editor = $read('app/views/clinica/partials/nota-subsecuente-editor.php');
$css = $read('public/assets/css/app.css');
$source = $view . "\n" . $editor;

$assert(str_contains($view, 'doctor-note-create-page'), 'La vista debe tener una clase scoped de creación.');
$assert(str_contains($view, 'Nueva nota subsecuente'), 'Debe conservarse el título de creación.');
$assert(str_contains($view, 'Volver al historial'), 'Debe existir una única acción de regreso al historial.');
$assert(substr_count($view, 'doctor-note-history-back') === 1, 'Debe existir un único botón de regreso en el hero.');
$assert(!str_contains($view, 'Contexto de la consulta'), 'No debe repetirse la tarjeta de contexto eliminada.');
$assert(str_contains($view, 'doctor-note-patient-strip'), 'Debe conservarse la franja compacta del paciente.');
$assert(str_contains($view, 'notaSubsecuenteForm'), 'Debe conservarse el formulario contractual.');

foreach (['TA', 'FrecCardiaca', 'Pulso', 'Spo2', 'Fio2', 'Ecog', 'Karnovsky', 'Peso', 'Talla', 'snow1', 'IncluirReceta', 'doctor-note-recipe-fields', 'Diagnostico', 'Temperatura', 'snow2', 'IncluirLaboratorio', 'doctor-note-lab-fields', 'IncluirLaboratorioAyuda', 'conteLaboratorio', 'detalleLaboratorio', 'Titulo', 'Archivo', 'snow3', 'FechaProximaCita', 'mensaje', 'nota-editor-config'] as $id) {
    $count = substr_count($source, 'id="' . $id . '"') + substr_count($source, "id='" . $id . "'");
    $assert($count === 1, 'El ID clínico debe existir una sola vez: ' . $id);
}

$assert(str_contains($source, 'AgregarLaboratorio'), 'Debe conservarse el flujo de laboratorio.');
$assert(str_contains($view, "'/clinica/laboratorio/insert-laboratorio'"), 'Debe conservarse el endpoint de laboratorio.');
$assert(str_contains($view, "'/clinica/paciente/insert-nota-subsecuente'"), 'Debe conservarse el endpoint de nota.');
$assert(str_contains($view, "'X-M03-CSRF'"), 'Debe conservarse el encabezado CSRF del laboratorio.');
$assert(str_contains($view, 'data-nota-csrf'), 'Debe conservarse el token CSRF de la nota.');
$assert(str_contains($view, 'frecCardiaca:'), 'Debe conservarse el mapeo frecCardiaca.');
$assert(str_contains($view, 'fc:'), 'Debe conservarse el mapeo condicional fc.');
$assert(str_contains($view, 'temperatura:'), 'Debe conservarse el mapeo de temperatura de receta.');
$assert(str_contains($view, 'idNota'), 'Debe conservarse el identificador de edición.');
$assert(str_contains($view, 'idReceta'), 'Debe conservarse el vínculo de receta.');
$assert(str_contains($view, 'proximacita'), 'Debe conservarse el campo de próxima cita.');

$assert(str_contains($editor, 'doctor-note-form-intro'), 'Debe existir la introducción clínica del formulario.');
$assert(str_contains($editor, 'doctor-note-optional-header'), 'Los complementos deben tener encabezados diferenciados.');
$assert(str_contains($editor, 'doctor-note-related-block'), 'Laboratorio debe separar consulta y adjunto.');
$assert(str_contains($editor, 'doctor-note-form-actions'), 'Debe existir un bloque final de acciones.');
$assert(str_contains($editor, 'aria-controls="doctor-note-recipe-fields"'), 'El checkbox debe controlar accesiblemente los campos de receta.');
$assert(str_contains($editor, 'data-recipe-fields'), 'Los campos de receta deben tener un contenedor identificable.');
$assert(str_contains($editor, 'aria-hidden="<?=$recetaSeleccionada ? \'false\' : \'true\';?>"'), 'Los campos de receta deben reflejar su estado accesible inicial.');
$assert(str_contains($editor, 'doctor-note-laboratory-toggle'), 'Laboratorio debe conservar un selector opcional propio.');
$assert(str_contains($editor, 'Agregar laboratorio relacionado'), 'El selector de laboratorio debe identificar su acción.');
$assert(str_contains($editor, 'aria-controls="doctor-note-lab-fields"'), 'El checkbox debe controlar accesiblemente los campos de laboratorio.');
$assert(str_contains($editor, 'data-laboratory-fields'), 'Los campos de laboratorio deben tener un contenedor identificable.');
$assert(str_contains($editor, 'hidden aria-hidden="true"'), 'Los campos de laboratorio deben iniciar ocultos.');
$assert(str_contains($editor, 'doctor-note-action-row'), 'Las acciones finales deben estar fuera de las tarjetas clínicas.');
$assert(substr_count($editor, 'onclick="AgregarNota()"') === 1, 'Debe existir un solo botón principal de guardado.');
$assert(str_contains($editor, '>Cancelar</a>'), 'Debe existir la acción secundaria Cancelar.');
$assert(str_contains($editor, 'data-note-exit'), 'Cancelar debe conservar la protección de salida.');
$assert(str_contains($editor, 'sanitizeMedicationHtml'), 'Debe conservarse la sanitización existente.');

$cssStart = strpos($css, '/* TH-DOCTOR-SUBSEQUENT-NOTE-CREATE-UX-REDESIGN-09:');
$assert($cssStart !== false, 'Debe existir el bloque CSS del rediseño.');
$cssBlock = substr($css, $cssStart, strpos($css, '/* UI-CLOSE-D09:', $cssStart) - $cssStart);
$assert(str_contains($cssBlock, 'body.app-shell-body.doctor-note-create-page'), 'El CSS nuevo debe estar scoped a la vista.');
$assert(!str_contains($cssBlock, 'height: 100%'), 'El rediseño no debe añadir alturas artificiales.');
$assert(str_contains($cssBlock, 'grid-template-columns'), 'Los complementos deben tener una distribución amplia.');
$assert(str_contains($cssBlock, '@media (max-width: 767px)'), 'Debe existir adaptación móvil.');

$remediationStart = strpos($css, '/* TH-DOCTOR-SUBSEQUENT-NOTE-CREATE-UI-REMEDIATION-09A:');
$assert($remediationStart !== false, 'Debe existir el bloque CSS de remediación 09A.');
$remediationBlock = substr($css, $remediationStart, strpos($css, '/* UI-CLOSE-D09:', $remediationStart) - $remediationStart);
$assert(str_contains($remediationBlock, 'display: grid;'), 'El selector de receta debe usar una distribución estable.');
$assert(str_contains($remediationBlock, 'margin: .15rem 0 0;'), 'La casilla debe cancelar el margen heredado de Bootstrap.');
$assert(str_contains($remediationBlock, 'color: #fff;'), 'Los estados morados deben tener texto blanco.');
$assert(str_contains($remediationBlock, 'stroke: currentColor;'), 'Los iconos deben seguir el color del botón.');
$assert(!str_contains($remediationBlock, 'margin-left: -'), 'La remediación no debe introducir márgenes negativos.');
$assert(str_contains($remediationBlock, '.btn-outline-primary:hover'), 'Debe cubrirse el hover de botones secundarios.');
$assert(str_contains($view, 'sincronizarCamposReceta'), 'El checkbox debe sincronizar la visibilidad de la receta.');
$assert(str_contains($view, "recetaSelector.addEventListener('change', sincronizarCamposReceta)"), 'El cambio de receta debe conservar interacción nativa.');
$assert(str_contains($view, 'laboratorioCargado'), 'La carga de laboratorio debe evitar solicitudes redundantes.');
$assert(str_contains($view, 'sincronizarCamposLaboratorio'), 'Laboratorio debe sincronizar su visibilidad desde el checkbox.');
$assert(str_contains($view, "laboratorioSelector.addEventListener('change', sincronizarCamposLaboratorio)"), 'El cambio de laboratorio debe conservar interacción nativa.');
$assert(substr_count($view, 'tableLaboratorio();') === 2, 'Laboratorio sólo debe cargarse al activarse o tras un guardado independiente.');
$assert((bool) preg_match('/\.doctor-note-entry-related\s*\{[^}]*display:\s*flex;/s', $cssBlock), 'Los bloques relacionados deben apilarse con altura natural.');
$assert((bool) preg_match('/\.doctor-note-entry-primary\s*\{[^}]*display:\s*flex;/s', $cssBlock), 'La nota y la receta deben separarse mediante un espaciado uniforme.');
$assert((bool) preg_match('/\.doctor-note-action-row\s*\{[^}]*width:\s*100%;/s', $cssBlock), 'La barra final debe ocupar el ancho completo.');

echo "TH_DOCTOR_SUBSEQUENT_NOTE_CREATE_UX_REDESIGN_09: PASS_STATIC\n";
