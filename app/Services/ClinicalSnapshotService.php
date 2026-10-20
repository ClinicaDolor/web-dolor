<?php
namespace App\Services;

use App\Config\Database;
use PDO;

/** Read-only, presentation-independent clinical snapshot. */
final class ClinicalSnapshotService
{
    private PDO $db;
    private array $unavailable = [];

    public function __construct(?PDO $db = null) { $this->db = $db ?: Database::getInstance(); }

    public function build(int $patientId): array
    {
        $patient = $this->one('pc_paciente', 'id', $patientId);
        if (!$patient) throw new \RuntimeException('PATIENT_NOT_FOUND');

        $definitions = [
            1 => ['name' => 'Identificación clínicamente pertinente', 'sources' => [['table'=>'pc_paciente','key'=>'id']]],
            2 => ['name' => 'Antecedentes familiares', 'sources' => [['table'=>'pc_antecedentes_familiares','key'=>'id_paciente']]],
            3 => ['name' => 'Antecedentes personales no patológicos', 'sources' => [['table'=>'pac_respuestas_paciente_modulo_3','key'=>'id_paciente']]],
            4 => ['name' => 'Antecedentes quirúrgicos', 'sources' => [['table'=>'pc_antecedentes_quirurgicos','key'=>'id_paciente']]],
            5 => ['name' => 'Antecedentes personales patológicos', 'sources' => [
                ['table'=>'pac_respuestas_paciente_modulo_5','key'=>'id_paciente'], ['table'=>'pac_enfermedades_respuestas_modulo_5','key'=>'id_paciente']]],
            6 => ['name' => 'Medicación actual', 'sources' => [['table'=>'pac_medicacion_actual_modulo_6','key'=>'id_paciente']]],
            7 => ['name' => 'Tratamientos farmacológicos para el dolor', 'sources' => [['table'=>'pac_respuestas_paciente_modulo_7','key'=>'id_paciente']]],
            8 => ['name' => 'Procedimientos para el dolor', 'sources' => [
                ['table'=>'pac_procedimientos_dolor_modulo_8','key'=>'id_paciente'], ['table'=>'pac_respuestas_paciente_modulo_8','key'=>'id_paciente']]],
            9 => ['name' => 'Evaluación del dolor', 'sources' => [['table'=>'pc_paciente_evaluacion_dolor','key'=>'id_paciente']]],
        ];
        $modules = [];
        foreach ($definitions as $number => $definition) {
            $records = [];
            foreach ($definition['sources'] as $source) {
                foreach ($this->many($source['table'], $source['key'], $patientId) as $row) {
                    // References are intentionally opaque and must not expose database IDs.
                    $rowId = (string)(count($records) + 1);
                    $data = $this->cleanRow($row, $number);
                    $records[] = [
                        'ref' => sprintf('M%02d:R%s', $number, str_pad($rowId, 3, '0', STR_PAD_LEFT)),
                        'source' => $source['table'],
                        'record_status' => $this->recordStatus($data, $number),
                        'data' => $data,
                    ];
                }
            }
            $modules[] = ['number'=>$number, 'name'=>$definition['name'], 'status'=>$this->status($records), 'records'=>$records];
        }
        return [
            'schema_version' => 'clinical-snapshot.v1',
            'generated_at' => gmdate('c'),
            'patient_id_internal' => $patientId,
            'context' => ['sex'=>$patient['sexo'] ?? null, 'age'=>$patient['edad'] ?? null, 'reason'=>$this->text($patient['motivo_atencion'] ?? null)],
            'modules' => $modules,
            'unavailable_sources' => array_values(array_unique($this->unavailable)),
            'additional_authorized_records' => [],
        ];
    }

    private function one(string $table, string $key, int $id): ?array
    {
        try { $s=$this->db->prepare("SELECT * FROM `$table` WHERE `$key`=:id LIMIT 1"); $s->execute(['id'=>$id]); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }
        catch (\Throwable $e) { $this->unavailable[]=$table; return null; }
    }
    private function many(string $table, string $key, int $id): array
    {
        try { $s=$this->db->prepare("SELECT * FROM `$table` WHERE `$key`=:id ORDER BY id ASC"); $s->execute(['id'=>$id]); return $s->fetchAll(PDO::FETCH_ASSOC); }
        catch (\Throwable $e) { $this->unavailable[]=$table; return []; }
    }
    private function cleanRow(array $row, int $moduleNumber): array
    {
        // Keep the clinical values needed for the snapshot, but remove direct and
        // quasi-identifiers before any snapshot can be sent to an external provider.
        unset($row['id'],$row['id_paciente'],$row['id_clinica'],$row['id_pregunta'],$row['id_tema'],$row['id_enfermedad'],$row['id_tratamiento'],$row['curp'],$row['email'],$row['telefono'],$row['celular'],$row['calle'],$row['num_interior'],$row['num_exterior'],$row['colonia'],$row['delegacion'],$row['cp'],$row['municipio'],$row['nombres'],$row['apellido_paterno'],$row['apellido_materno'],$row['res_nombre'],$row['res_telefono'],$row['cuidador'],$row['cuidador_telefono']);
        // M02 stores the catalog disease on a row as soon as the checkbox is
        // rendered. It is not a patient statement until detalle is answered.
        if ($moduleNumber === 2 && $this->blank($row['detalle'] ?? null)) {
            unset($row['enfermedad'], $row['tipo'], $row['especificar']);
        }
        if ($moduleNumber === 3 && $this->blank($row['estado_respuesta'] ?? null)) {
            $textualState = \App\Models\AntecedentesNoPatologicosModel::canonicalM03UnknownState((string)($row['respuesta'] ?? ''));
            if ($textualState !== null) $row['estado_respuesta'] = $textualState;
        }
        foreach ($row as $key=>$value) { if (is_string($value)) $row[$key]=mb_substr(strip_tags($value),0,2000); }
        return $row;
    }
    private function recordStatus(array $data, int $moduleNumber): string
    {
        return $this->hasClinicalData($data, $moduleNumber) ? 'documented' : 'unanswered';
    }
    private function hasClinicalData(array $data, int $moduleNumber): bool
    {
        if ($moduleNumber === 3 && in_array(strtoupper(trim((string)($data['estado_respuesta'] ?? ''))), ['UNKNOWN', 'NOT_REMEMBERED'], true)) return true;
        $fields = [
            1 => ['sexo','edad','estado_civil','lugar_origen','lugar_residencia','ocupacion','motivo_atencion','fecha_alta','fecha_nacimiento','ultima_actualizacion'],
            2 => ['detalle'],
            3 => ['respuesta'],
            4 => ['fecha','cirugia','observaciones'],
            5 => ['respuesta','year_diagnostico'],
            6 => ['medicamento','descripcion','tiempo_uso','dosis','medico'],
            7 => ['respuesta','dosis','resultados','descripcion','consumo'],
            8 => ['procedimiento','fecha','resultados','descripcion','utilizo','comentarios'],
            9 => ['dolor','tiempo_dolor','descripcion','incremento'],
        ][$moduleNumber] ?? array_keys($data);
        foreach ($fields as $field) if (!$this->blank($data[$field] ?? null)) return true;
        return false;
    }
    private function status(array $records): string
    {
        if (!$records) return 'absent';
        foreach ($records as $record) if (($record['record_status'] ?? null) === 'documented') return 'documented';
        return 'incomplete';
    }
    private function blank($value): bool { return $value === null || trim((string)$value) === ''; }
    private function text($value): ?string { return ($value === null || trim((string)$value)==='') ? null : mb_substr(strip_tags((string)$value),0,500); }
}
