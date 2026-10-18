<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$viewPath = $root . '/app/views/clinica/pacientes-recetas.php';
$modelPath = $root . '/app/Models/RecetaModel.php';
$view = file_get_contents($viewPath);
$model = file_get_contents($modelPath);
$failures = [];

$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(is_string($view), 'prescription view must be readable');
$assert(is_string($model), 'prescription model must be readable');

if (is_string($view)) {
    $insertion = strpos($view, 'resultsContainer.innerHTML = data;');
    $normalization = strpos($view, 'eliminarSeparadorFechaHora(resultsContainer);');

    $assert($insertion !== false && $normalization !== false && $normalization > $insertion, 'separator normalization must run after detail HTML insertion');
    $assert(str_contains($view, 'function eliminarSeparadorFechaHora(container)'), 'detail separator normalization function must remain present');
    $assert(str_contains($view, 'label.textContent.trim() === \'Fecha:\''), 'normalization must identify the dynamic Fecha label');
    $assert(str_contains($view, 'label.textContent.trim() === \'Hora:\''), 'normalization must identify the dynamic Hora label');
    $assert(str_contains($view, 'node.nodeType === Node.TEXT_NODE'), 'normalization must target text nodes only');
    $assert(str_contains($view, '/^\\s*,\\s*$/'), 'normalization must match only a comma-only separator node');
    $assert(str_contains($view, 'X-M03-CSRF'), 'existing CSRF flow must remain');
    $assert(str_contains($view, 'function imprimirReceta'), 'existing print flow must remain');
    $assert(str_contains($view, 'function generarPDF'), 'existing preview/PDF flow must remain');

    foreach (['agregar-receta-modal', 'detalle-receta-modal', 'modalImprimirReceta'] as $modalId) {
        $assert(substr_count($view, 'id="' . $modalId . '"') === 1, $modalId . ' must remain unique');
    }
}

if (is_string($model)) {
    $assert(str_contains($model, '</label>, <small class="text-primary">Hora: </small> <label'), 'the historical comma source must be identified in the model output');
    $assert(str_contains($model, 'sanitizeMedicationHtml'), 'the existing response sanitization must remain in the model');
}

if (class_exists(DOMDocument::class)) {
    $html = '<div id="fixture"><div><small class="text-primary">Fecha: </small> <label class="fs-5">15/10/2026</label>, <small class="text-primary">Hora: </small> <label class="fs-5">02:05 pm</label></div><div>Diagnóstico: dolor, fiebre</div></div>';
    $document = new DOMDocument();
    $document->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $fixture = $document->getElementById('fixture');
    $xpath = new DOMXPath($document);
    $fechaLabel = $xpath->query('.//small[normalize-space(.)="Fecha:"]', $fixture)?->item(0);
    $horaLabel = $xpath->query('.//small[normalize-space(.)="Hora:"]', $fixture)?->item(0);
    $removed = false;

    $assert($fechaLabel instanceof DOMElement && $horaLabel instanceof DOMElement, 'synthetic render fixture must expose Fecha and Hora labels');
    if ($fechaLabel instanceof DOMElement && $horaLabel instanceof DOMElement && $fechaLabel->parentNode === $horaLabel->parentNode) {
        for ($node = $fechaLabel->nextSibling; $node !== null && $node !== $horaLabel; $node = $node->nextSibling) {
            if ($node->nodeType === XML_TEXT_NODE && preg_match('/^\s*,\s*$/u', $node->nodeValue ?? '') === 1) {
                $node->nodeValue = ' ';
                $removed = true;
            }
        }
    }

    $rendered = $document->saveHTML($fixture);
    $assert($removed, 'synthetic render must remove the comma-only separator');
    $assert(!str_contains($rendered, '</label>, <small class="text-primary">Hora:'), 'synthetic render must not place a comma before Hora');
    $assert(str_contains($rendered, '15/10/2026'), 'synthetic render must preserve the dynamic date');
    $assert(str_contains($rendered, '02:05 pm'), 'synthetic render must preserve the dynamic time');
    $assert(str_contains($rendered, 'dolor, fiebre'), 'synthetic render must preserve legitimate clinical punctuation');
} else {
    $assert(false, 'DOM extension is required for the synthetic render assertion');
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Prescription detail comma contract: PASS" . PHP_EOL;
