<?php

declare(strict_types=1);

/*
 * Catálogo exclusivamente sintético para P07. Los textos llevan el prefijo
 * de prueba y no representan datos clínicos reales ni modifican el catálogo
 * de producción.
 */
return [
    'themes' => [
        ['id' => 1, 'tema' => 'P07 sintético: antecedentes 1'],
        ['id' => 2, 'tema' => 'P07 sintético: antecedentes 2'],
    ],
    'questions' => [
        ['id' => 501, 'id_tema' => 1, 'pregunta' => 'P07 sintético: respuesta de prueba', 'tipo' => 'select'],
        ['id' => 502, 'id_tema' => 2, 'pregunta' => 'P07 sintético: campo de texto', 'tipo' => 'input'],
    ],
    'diseases' => [
        ['id' => 1, 'descripcion' => 'Diabetes Mellitus'],
        ['id' => 2, 'descripcion' => 'Enfermedad pulmonar'],
    ],
];
