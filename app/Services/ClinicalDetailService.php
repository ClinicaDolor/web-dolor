<?php
namespace App\Services;

use App\Config\Database;
use PDO;

/** Read-only, human-readable presentation detail for the authorized Doctor. */
final class ClinicalDetailService
{
    private PDO $db;
    private array $columnAvailability = [];

    public function __construct(?PDO $db = null) { $this->db = $db ?: Database::getInstance(); }

    public function build(int $patientId): array
    {
        if ($patientId < 1) throw new \InvalidArgumentException('PATIENT_INVALID');
        $patient = $this->one('SELECT fecha_alta, fecha_nacimiento, edad, sexo, estado_civil, lugar_origen, lugar_residencia, ocupacion, motivo_atencion, ultima_actualizacion FROM pc_paciente WHERE id = :id LIMIT 1', $patientId);
        if (!$patient) throw new \RuntimeException('PATIENT_NOT_FOUND');

        $m03Rows = $this->many('SELECT t.tema, q.pregunta, r.respuesta, ' . ($this->hasColumn('pac_respuestas_paciente_modulo_3', 'estado_respuesta') ? 'r.estado_respuesta' : 'NULL') . ' AS estado_respuesta, ' . ($this->hasColumn('pac_respuestas_paciente_modulo_3', 'origen_respuesta') ? 'r.origen_respuesta' : 'NULL') . ' AS origen_respuesta FROM pac_respuestas_paciente_modulo_3 r INNER JOIN pac_preguntas_modulo_3 q ON q.id = r.id_pregunta INNER JOIN pac_temas_modulo_3 t ON t.id = q.id_tema WHERE r.id_paciente = :id ORDER BY t.id, q.id', $patientId);
        foreach ($m03Rows as &$m03Row) {
            if ($m03Row['estado_respuesta'] === null || trim((string)$m03Row['estado_respuesta']) === '') {
                $textualState = \App\Models\AntecedentesNoPatologicosModel::canonicalM03UnknownState((string)($m03Row['respuesta'] ?? ''));
                if ($textualState !== null) $m03Row['estado_respuesta'] = $textualState;
            }
        }
        unset($m03Row);

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
                'M03' => $this->moduleFromRows('M03', 'Antecedentes personales no patológicos', $m03Rows, [
                    'tema' => 'Tema', 'pregunta' => 'Pregunta', 'respuesta' => 'Respuesta', 'estado_respuesta' => 'Estado de respuesta', 'origen_respuesta' => 'Procedencia'
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
                'M07' => $this->canonicalM07Module($patientId) ?? $this->moduleFromRows('M07', 'Tratamientos farmacológicos para el dolor', $this->many('SELECT t.tema, q.pregunta, r.respuesta, r.dosis, r.resultados, r.descripcion, r.consumo FROM pac_respuestas_paciente_modulo_7 r INNER JOIN pac_preguntas_modulo_7 q ON q.id = r.id_pregunta INNER JOIN pac_temas_modulo_7 t ON t.id = q.id_tema WHERE r.id_paciente = :id ORDER BY t.id, q.id', $patientId), [
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

    /**
     * Doctor and summary readers must consume the same M07 DTO as the
     * conversational and P09 readers whenever the canonical extension exists.
     * A legacy projection remains only as an explicit compatibility fallback
     * for databases that have not received the additive schema.
     */
    private function canonicalM07Module(int $patientId): ?array
    {
        $patient = $this->one('SELECT id_clinica FROM pc_paciente WHERE id = :id LIMIT 1', $patientId);
        $clinicId = (int)($patient['id_clinica'] ?? 0);
        if ($clinicId < 1) return null;

        try {
            $state = (new M07CanonicalService($this->db))->read($patientId, $clinicId, 'doctor');
        } catch (M07CanonicalException $exception) {
            if ($exception->codeName === 'M07_SCHEMA_UNAVAILABLE') return null;
            throw $exception;
        }

        $records = [];
        foreach ($state['records'] ?? [] as $record) {
            $fields = is_array($record['fields'] ?? null) ? $record['fields'] : [];
            $records[] = [
                'meta' => [
                    'source' => 'M07CanonicalService',
                    'source_kind' => $record['source'] ?? 'CATALOG',
                    'response_id' => (int)($record['id'] ?? 0),
                    'question_id' => (int)($record['question_id'] ?? 0),
                    'theme_id' => (int)($record['theme_id'] ?? 0),
                    'status' => $record['status'] ?? 'pending',
                    'pending_reason' => $record['pending_reason'] ?? null,
                    'version' => (int)($record['version'] ?? 0),
                    'states' => $record['states'] ?? [],
                ],
                'groups' => [[
                    'title' => (string)($record['legacy_projection']['theme'] ?? 'M07'),
                    'fields' => $this->fields([
                        'pregunta' => $record['name'] ?? '',
                        'respuesta' => $fields['respuesta'] ?? '',
                        'dosis' => $fields['dosis'] ?? '',
                        'resultados' => $fields['resultados'] ?? '',
                        'descripcion' => $fields['descripcion'] ?? '',
                        'consumo' => $fields['consumo'] ?? '',
                        'eficacia' => $record['efficacy']['code'] ?? null,
                        'tolerabilidad' => $record['tolerability']['code'] ?? null,
                    ], [
                        'pregunta' => 'Pregunta',
                        'respuesta' => 'Respuesta',
                        'dosis' => 'Dosis',
                        'resultados' => 'Resultado documentado',
                        'descripcion' => 'Información complementaria',
                        'consumo' => 'Consumo registrado',
                        'eficacia' => 'Eficacia',
                        'tolerabilidad' => 'Tolerabilidad',
                    ]),
                ]],
            ];
        }

        foreach ($state['other_medications'] ?? [] as $other) {
            $records[] = [
                'meta' => [
                    'source' => 'M07CanonicalService',
                    'source_kind' => 'NON_CATALOGUED',
                    'entry_key' => $other['entry_key'] ?? null,
                    'theme_id' => (int)($other['theme_id'] ?? 0),
                    'status' => $other['status'] ?? 'pending',
                    'version' => (int)($other['version'] ?? 0),
                ],
                'groups' => [[
                    'title' => 'Medicamento no catalogado',
                    'fields' => $this->fields([
                        'nombre' => $other['name'] ?? $other['nombre'] ?? '',
                        'estado' => $other['status'] ?? 'pending',
                    ], [
                        'nombre' => 'Nombre registrado',
                        'estado' => 'Estado canónico',
                    ]),
                ]],
            ];
        }

        return $this->module('M07', 'Tratamientos farmacológicos para el dolor', $records);
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

    private function hasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, $this->columnAvailability)) return $this->columnAvailability[$key];
        try {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name');
            $stmt->execute([':table_name' => $table, ':column_name' => $column]);
            return $this->columnAvailability[$key] = (int)$stmt->fetchColumn() === 1;
        } catch (\Throwable) {
            return $this->columnAvailability[$key] = false;
        }
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
