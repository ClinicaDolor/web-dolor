<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/app/Controllers/PatientInterviewController.php');
$view = file_get_contents($root . '/app/views/paciente/entrevista.php');

$assertions = [
    str_contains($controller, "header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0')"),
    str_contains($controller, "header('Pragma: no-cache')"),
    str_contains($view, '$versionedAsset = static function'),
    str_contains($view, 'filemtime($filesystemPath)'),
    str_contains($view, '<?=$bootstrapCssUrl;?>'),
    str_contains($view, '<?=$perfectScrollbarCssUrl;?>'),
    str_contains($view, '<?=$appCssUrl;?>'),
    str_contains($view, "'assets/libs/perfect-scrollbar/perfect-scrollbar.css'"),
    str_contains($view, '$logoUrl = $versionedAsset'),
    str_contains($view, 'href="<?=$logoUrl;?>"'),
    str_contains($view, 'data-m02-quick data-value="Sí"'),
    !str_contains($view, 'data-m02-quick data-value="No"'),
    substr_count($view, 'data-m02-safe-no') === 2,
    str_contains($view, 'data-m02-interpret hidden'),
    str_contains($view, 'const interpretM02 = async'),
    str_contains($view, "input.dispatchEvent(new Event('input', {bubbles: true}))"),
    str_contains($view, 'La respuesta cambió. Revisa la nueva propuesta; todavía no se guardó ningún dato.'),
    str_contains($view, 'data-m02-defer'),
    str_contains($view, 'Pregunta M02 pendiente. No se guardó ni se convirtió en «No».'),
    substr_count($view, "document.querySelectorAll('[data-m02-interpret]')") === 1,
    substr_count($view, "document.querySelectorAll('[data-m02-quick]')") === 1,
    substr_count($view, "document.querySelectorAll('[data-m02-safe-no]')") === 1,
    substr_count($view, 'app.css?v=') === 0,
    str_contains($view, 'Ya existe una respuesta escrita. No se reemplazó; revísala o bórrala antes de usar una respuesta rápida.'),
    str_contains($view, 'Hay una respuesta o propuesta pendiente. Revísala o cancélala antes de guardar una respuesta negativa.'),
];

if (in_array(false, $assertions, true)) {
    fwrite(STDERR, "M02 UX/assets contract: FAIL\n" . json_encode($assertions) . "\n");
    exit(1);
}

echo 'M02 UX/assets contract: PASS (' . count($assertions) . " assertions; interview scope; no database writes)\n";
