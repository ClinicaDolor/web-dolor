<?php

namespace App\Services;

use App\Config\Database;
use PDO;

/** Read-only M01 adapter for the independent conversation. */
final class ConversationalM01Adapter
{
    public function __construct(private ?PDO $db = null) {}

    public function read(int $patientId): array
    {
        if ($patientId < 1) throw new \InvalidArgumentException('PATIENT_INVALID');
        $db = $this->db ?: Database::getInstance();
        $stmt = $db->prepare(
            'SELECT fecha_nacimiento, edad, sexo, estado_civil, lugar_origen,
                    lugar_residencia, ocupacion, motivo_atencion
             FROM pc_paciente WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $patientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new \RuntimeException('PATIENT_NOT_FOUND');

        $labels = [
            'fecha_nacimiento' => 'Fecha de nacimiento',
            'edad' => 'Edad',
            'sexo' => 'Sexo',
            'estado_civil' => 'Estado civil',
            'lugar_origen' => 'Lugar de origen',
            'lugar_residencia' => 'Lugar de residencia',
            'ocupacion' => 'Ocupación',
            'motivo_atencion' => 'Motivo de atención',
        ];
        $fields = [];
        foreach ($labels as $key => $label) {
            $value = trim((string)($row[$key] ?? ''));
            if ($value === '') $value = 'No registrado';
            if ($key === 'sexo') $value = ['M' => 'Masculino', 'F' => 'Femenino'][$value] ?? $value;
            $fields[] = ['key' => $key, 'label' => $label, 'value' => mb_substr(strip_tags($value), 0, 500), 'read_only' => true];
        }
        return [
            'key' => 'M01',
            'title' => 'Identificación clínicamente pertinente',
            'status' => 'read_only',
            'fields' => $fields,
            'write_enabled' => false,
        ];
    }

    public function interpretField(string $field, string $utterance): array
    {
        $allowed = ['fecha_nacimiento', 'edad', 'sexo', 'estado_civil', 'lugar_origen', 'lugar_residencia', 'ocupacion', 'motivo_atencion'];
        $utterance = trim($utterance);
        if (!in_array($field, $allowed, true) || $utterance === '') {
            throw new \InvalidArgumentException('Campo M01 o respuesta no válida.');
        }
        return [
            'mode' => 'deterministic',
            'kind' => 'm01_read_only_proposal',
            'field' => $field,
            'structured_value' => $utterance,
            'interpretation' => $utterance,
            'can_confirm' => false,
            'write_enabled' => false,
            'message' => 'Propuesta solo informativa. M01 no se modifica en esta fase.',
        ];
    }
}
