<?php
namespace App\Services;

use App\Config\Database;
use PDO;

/** Read-only metadata and clinical context outside the M01-M09 summary contract. */
final class ClinicalSupplementService
{
    private PDO $db;
    private int $limit;

    public function __construct(?PDO $db = null, int $limit = 5)
    {
        $this->db = $db ?: Database::getInstance();
        $this->limit = max(1, min(10, $limit));
    }

    public function forPatient(int $patientId): array
    {
        if ($patientId < 1) throw new \InvalidArgumentException('PATIENT_INVALID');
        return [
            'notes' => $this->notes($patientId),
            'prescriptions' => $this->prescriptions($patientId),
            'laboratories' => $this->laboratories($patientId),
            'cofepris' => $this->cofepris($patientId),
        ];
    }

    private function notes(int $patientId): array
    {
        return $this->limitedRows('SELECT id, fecha_hora, contenido, ta, frec_cardiaca, pulso, spo2, fio2, ecog, karnovsky, peso, talla, proxima_cita FROM nota_subsecuente WHERE id_paciente = :id_paciente ORDER BY fecha_hora DESC LIMIT ' . ($this->limit + 1), $patientId);
    }

    private function prescriptions(int $patientId): array
    {
        return $this->limitedRows('SELECT id, fecha_hora, diagnostico, medicamento, ta, fc, spo2, temperatura FROM receta_medica WHERE id_paciente = :id_paciente ORDER BY fecha_hora DESC LIMIT ' . ($this->limit + 1), $patientId);
    }

    private function laboratories(int $patientId): array
    {
        return $this->limitedRows('SELECT id, fecha_hora, nombre, tipo, titulo, descripcion FROM laboratorio WHERE id_paciente = :id_paciente ORDER BY fecha_hora DESC LIMIT ' . ($this->limit + 1), $patientId);
    }

    private function cofepris(int $patientId): array
    {
        return $this->limitedRows('SELECT id, fecha_hora, diagnostico, medicamento, presentacion, dosificacion, num_cajas, num_dias, via_administracion, surtido FROM cofepris WHERE id_paciente = :id_paciente ORDER BY fecha_hora DESC LIMIT ' . ($this->limit + 1), $patientId);
    }

    private function limitedRows(string $sql, int $patientId): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_paciente' => $patientId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hasMore = count($rows) > $this->limit;
        if ($hasMore) array_pop($rows);
        return ['records' => $rows, 'has_more' => $hasMore, 'limit' => $this->limit];
    }
}
