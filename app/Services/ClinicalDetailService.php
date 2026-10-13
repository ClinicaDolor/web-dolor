<?php
namespace App\Services;

use App\Config\Database;
use PDO;

/** Read-only, human-readable presentation detail for the authorized Doctor. */
final class ClinicalDetailService
{
    private PDO $db;

    public function __construct(?PDO $db = null) { $this->db = $db ?: Database::getInstance(); }

    public function build(int $patientId): array
    {
        if ($patientId < 1) throw new \InvalidArgumentException('PATIENT_INVALID');
        $patient = $this->one('SELECT fecha_alta, fecha_nacimiento, edad, sexo, estado_civil, lugar_origen, lugar_residencia, ocupacion, motivo_atencion, ultima_actualizacion FROM pc_paciente WHERE id = :id LIMIT 1', $patientId);
        if (!$patient) throw new \RuntimeException('PATIENT_NOT_FOUND');

        $m01Records = [[
            'groups' => [
                ['title' => 'Datos generales', 'fields' => $this->fields($patient, [
                    'fecha_alta' => 'Fecha de registro', 'fecha_nacimiento' => 'Fecha de nacimiento', 'edad' => 'Edad', 'sexo' => 'Sexo',
                    'estado_civil' => 'Estado civil', 'lugar_origen' => 'Lugar de origen', 'lugar_residencia' => 'Lugar de residencia', 'ocupacion' => 'Ocupación', 'ultima_actualizacion' => 'Última actualización'
                ], ['fecha_alta', 'fecha_nacimiento', 'ultima_actualizacion'], true)],
                ['title' => 'Motivo de atención', 'fields' => $this->fields($patient, ['motivo_atencion' => 'Motivo de atención'])]
            ]
        ]];

        return [
            'schema_version' => 'clinical-detail.v1',
            'modules' => [
                'M01' => $this->module('M01', 'Identificación clínicamente pertinente', $m01Records),
                'M02' => $this->moduleFromRows('M02', 'Antecedentes familiares', $this->many('SELECT enfermedad, tipo, detalle, especificar FROM pc_antecedentes_familiares WHERE id_paciente = :id ORDER BY id ASC', $patientId), [
                    'enfermedad' => 'Enfermedad', 'tipo' => 'Tipo de diabetes', 'detalle' => 'Respuesta documentada', 'especificar' => 'Parentesco especificado'
                ]),
                'M03' => $this->moduleFromRows('M03', 'Antecedentes personales no patológicos', $this->many('SELECT t.tema, q.pregunta, r.respuesta FROM pac_respuestas_paciente_modulo_3 r INNER JOIN pac_preguntas_modulo_3 q ON q.id = r.id_pregunta INNER JOIN pac_temas_modulo_3 t ON t.id = q.id_tema WHERE r.id_paciente = :id ORDER BY t.id, q.id', $patientId), [
                    'tema' => 'Tema', 'pregunta' => 'Pregunta', 'respuesta' => 'Respuesta'
                ]),
                'M04' => $this->moduleFromRows('M04', 'Antecedentes quirúrgicos', $this->many('SELECT fecha, cirugia, observaciones FROM pc_antecedentes_quirurgicos WHERE id_paciente = :id ORDER BY fecha DESC, id DESC', $patientId), [
                    'cirugia' => 'Procedimiento', 'fecha' => 'Fecha', 'observaciones' => 'Observaciones'
                ]),
                'M05' => $this->moduleFromRows('M05', 'Antecedentes personales patológicos', array_merge(
                    $this->many('SELECT t.tema, q.pregunta, r.respuesta, NULL AS enfermedad, NULL AS year_diagnostico FROM pac_respuestas_paciente_modulo_5 r INNER JOIN pac_preguntas_modulo_5 q ON q.id = r.id_pregunta INNER JOIN pac_temas_modulo_5 t ON t.id = q.id_tema WHERE r.id_paciente = :id ORDER BY t.id, q.id', $patientId),
                    $this->many('SELECT NULL AS tema, NULL AS pregunta, r.respuesta, e.descripcion AS enfermedad, r.year_diagnostico FROM pac_enfermedades_respuestas_modulo_5 r INNER JOIN pac_enfermedades_modulo_5 e ON e.id = r.id_enfermedad WHERE r.id_paciente = :id ORDER BY r.id ASC', $patientId)
                ), [
                    'tema' => 'Categoría', 'pregunta' => 'Pregunta', 'respuesta' => 'Respuesta', 'enfermedad' => 'Enfermedad', 'year_diagnostico' => 'Año de diagnóstico'
                ]),
                'M06' => $this->moduleFromRows('M06', 'Medicación actual', $this->many('SELECT medicamento, descripcion, tiempo_uso, dosis, medico FROM pac_medicacion_actual_modulo_6 WHERE id_paciente = :id ORDER BY id ASC', $patientId), [
                    'medicamento' => 'Medicamento', 'descripcion' => 'Información complementaria', 'tiempo_uso' => 'Tiempo de uso', 'dosis' => 'Dosis', 'medico' => 'Médico registrado'
                ]),
                'M07' => $this->moduleFromRows('M07', 'Tratamientos farmacológicos para el dolor', $this->many('SELECT t.tema, q.pregunta, r.respuesta, r.dosis, r.resultados, r.descripcion, r.consumo FROM pac_respuestas_paciente_modulo_7 r INNER JOIN pac_preguntas_modulo_7 q ON q.id = r.id_pregunta INNER JOIN pac_temas_modulo_7 t ON t.id = q.id_tema WHERE r.id_paciente = :id ORDER BY t.id, q.id', $patientId), [
                    'tema' => 'Tema', 'pregunta' => 'Pregunta', 'respuesta' => 'Respuesta', 'dosis' => 'Dosis', 'resultados' => 'Resultado documentado', 'descripcion' => 'Información complementaria', 'consumo' => 'Consumo registrado'
                ]),
                'M08' => $this->moduleFromRows('M08', 'Procedimientos para el dolor', array_merge(
                    $this->many("SELECT procedimiento, fecha, resultados, descripcion, NULL AS utilizo, NULL AS comentarios, 'procedimiento_registrado' AS source_kind FROM pac_procedimientos_dolor_modulo_8 WHERE id_paciente = :id ORDER BY fecha DESC, id DESC", $patientId),
                    $this->many("SELECT t.procedimiento, NULL AS fecha, NULL AS resultados, r.descripcion, r.utilizo, r.comentarios, 'tratamiento_cuestionario' AS source_kind FROM pac_respuestas_paciente_modulo_8 r INNER JOIN pac_tratamientos_dolor_modulo_8 t ON t.id = r.id_tratamiento WHERE r.id_paciente = :id ORDER BY r.id ASC", $patientId)
                ), [
                    'procedimiento' => 'Procedimiento o tratamiento', 'fecha' => 'Fecha', 'resultados' => 'Resultado documentado', 'descripcion' => 'Descripción', 'utilizo' => 'Utilizó', 'comentarios' => 'Observaciones'
                ]),
                'M09' => $this->moduleFromRows('M09', 'Evaluación del dolor', $this->many('SELECT dolor, tiempo_dolor, descripcion, incremento FROM pc_paciente_evaluacion_dolor WHERE id_paciente = :id ORDER BY id DESC', $patientId), [
                    'dolor' => 'Presencia de dolor documentada', 'tiempo_dolor' => 'Tiempo de evolución', 'descripcion' => 'Características y localización', 'incremento' => 'Factores relacionados'
                ]),
            ],
        ];
    }

    private function module(string $key, string $title, array $records): array
    {
        return ['key' => $key, 'title' => $title, 'records' => array_map(fn($record, $index) => array_filter(['ref' => $key . ':R' . str_pad((string)($index + 1), 3, '0', STR_PAD_LEFT), 'meta' => $record['meta'] ?? null, 'groups' => $record['groups']], static fn($value) => $value !== null), $records, array_keys($records))];
    }

    private function moduleFromRows(string $key, string $title, array $rows, array $labels): array
    {
        $records = [];
        foreach ($rows as $row) {
            $meta = [];
            if (isset($row['source_kind'])) { $meta['source_kind'] = (string)$row['source_kind']; unset($row['source_kind']); }
            $records[] = ['meta' => $meta ?: null, 'groups' => [['title' => null, 'fields' => $this->fields($row, $labels)]]];
        }
        return $this->module($key, $title, $records);
    }

    private function fields(array $row, array $labels, array $dateFields = [], bool $omitEmpty = false): array
    {
        $fields = [];
        foreach ($labels as $source => $label) {
            $value = $row[$source] ?? null;
            if ($value === null || trim((string)$value) === '') { if (!$omitEmpty) $fields[] = ['label' => $label, 'value' => 'No registrado']; continue; }
            if (in_array($source, $dateFields, true)) $value = $this->date($value);
            if ($source === 'sexo') $value = ['M' => 'Masculino', 'F' => 'Femenino'][$value] ?? $value;
            $fields[] = ['label' => $label, 'value' => mb_substr(strip_tags((string)$value), 0, 2000)];
        }
        return $fields;
    }

    private function date($value): string
    {
        try { return (new \DateTime((string)$value))->format('d/m/Y'); } catch (\Throwable) { return (string)$value; }
    }

    private function one(string $sql, int $patientId): ?array
    {
        $stmt = $this->db->prepare($sql); $stmt->execute([':id' => $patientId]); return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function many(string $sql, int $patientId): array
    {
        try {
            $stmt = $this->db->prepare($sql); $stmt->execute([':id' => $patientId]); return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }
}
