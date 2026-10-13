<?php

declare(strict_types=1);

/**
 * Catálogo P05 extraído de los SQL aprobados recibidos fuera del repositorio.
 * No contiene respuestas, pacientes ni datos clínicos de expedientes.
 */
return [
    'themes' => [
        ['id' => 1, 'tema' => 'Tabaquismo'],
        ['id' => 2, 'tema' => 'Alcoholismo'],
        ['id' => 3, 'tema' => 'Ejercicio'],
        ['id' => 4, 'tema' => 'Estres'],
        ['id' => 5, 'tema' => 'Drogas'],
        ['id' => 6, 'tema' => 'Dormir'],
        ['id' => 7, 'tema' => 'Grupo Sanguineo'],
    ],
    'questions' => [
        ['id' => 1, 'id_tema' => 1, 'pregunta' => '¿Usted fuma cigarros de nicotina?', 'tipo' => 'select'],
        ['id' => 2, 'id_tema' => 1, 'pregunta' => '¿Desde qué edad empezó a fumar?', 'tipo' => 'input'],
        ['id' => 3, 'id_tema' => 1, 'pregunta' => '¿Durante cuánto tiempo ha fumado?', 'tipo' => 'input'],
        ['id' => 4, 'id_tema' => 1, 'pregunta' => '¿Cuántos cigarrillos consume en promedio por día?', 'tipo' => 'input'],
        ['id' => 5, 'id_tema' => 1, 'pregunta' => 'Si ya dejó de fumar, ¿hace cuánto tiempo lo suspendió?', 'tipo' => 'input'],
        ['id' => 6, 'id_tema' => 2, 'pregunta' => '¿Usted consume alcohol?', 'tipo' => 'select'],
        ['id' => 8, 'id_tema' => 2, 'pregunta' => '¿Con qué frecuencia? ', 'tipo' => 'input'],
        ['id' => 9, 'id_tema' => 2, 'pregunta' => '¿Durante cuánto tiempo ha consumido alcohol?', 'tipo' => 'input'],
        ['id' => 10, 'id_tema' => 2, 'pregunta' => '¿Qué tipo de bebida alcohólica consume con mayor frecuencia?', 'tipo' => 'input'],
        ['id' => 11, 'id_tema' => 2, 'pregunta' => 'Si ya dejó de consumir alcohol, ¿Hace cuánto tiempo lo suspendió?', 'tipo' => 'input'],
        ['id' => 12, 'id_tema' => 3, 'pregunta' => '¿Practica algún ejercicio?', 'tipo' => 'select'],
        ['id' => 13, 'id_tema' => 3, 'pregunta' => '¿Con qué frecuencia?', 'tipo' => 'input'],
        ['id' => 14, 'id_tema' => 3, 'pregunta' => '¿Hace cuánto tiempo comenzó a ejercitarse regularmente?', 'tipo' => 'input'],
        ['id' => 15, 'id_tema' => 3, 'pregunta' => '¿Qué tipo de ejercicio practica principalmente?', 'tipo' => 'input'],
        ['id' => 16, 'id_tema' => 4, 'pregunta' => '¿Considera que actualmente experimenta estrés?', 'tipo' => 'select'],
        ['id' => 17, 'id_tema' => 4, 'pregunta' => 'Si experimenta estrés, ¿Cómo describiría su nivel de estrés: leve, moderado o severo?', 'tipo' => 'input'],
        ['id' => 18, 'id_tema' => 5, 'pregunta' => '¿Usted consume algún tipo de droga? ', 'tipo' => 'select'],
        ['id' => 19, 'id_tema' => 5, 'pregunta' => '¿Con qué frecuencia?', 'tipo' => 'input'],
        ['id' => 20, 'id_tema' => 5, 'pregunta' => '¿Qué tipo de droga consume con mayor frecuencia?', 'tipo' => 'input'],
        ['id' => 21, 'id_tema' => 6, 'pregunta' => '¿Usted consume algún tipo de medicación para poder dormir?', 'tipo' => 'select'],
        ['id' => 23, 'id_tema' => 6, 'pregunta' => '¿Con qué frecuencia? ', 'tipo' => 'input'],
        ['id' => 24, 'id_tema' => 6, 'pregunta' => '¿Cuál es el nombre del medicamento que utiliza?', 'tipo' => 'input'],
        ['id' => 26, 'id_tema' => 6, 'pregunta' => '¿Cuánta dosis utiliza de ese medicamento?', 'tipo' => 'input'],
        ['id' => 27, 'id_tema' => 7, 'pregunta' => '¿Cuál es su grupo sanguíneo?', 'tipo' => 'input'],
        ['id' => 28, 'id_tema' => 7, 'pregunta' => '¿Cuál es su factor RH?', 'tipo' => 'input'],
    ],
];
