<?php

namespace App\Services;

use App\Config\Database;
use App\Models\EvaluacionDolorModel;
use PDO;

/** Chat-only safety layer around the existing M09 PNG persistence service. */
final class ConversationalM09MapAdapter
{
    public function __construct(private ?PDO $db = null, private ?EvaluacionDolorModel $model = null) {}

    private function connection(): PDO { return $this->db ?: Database::getInstance(); }
    private function imageModel(): EvaluacionDolorModel { return $this->model ?: new EvaluacionDolorModel($this->connection()); }

    public function read(int $patientId): array
    {
        if ($patientId < 1) throw new \InvalidArgumentException('PATIENT_INVALID');
        $model = $this->imageModel();
        $images = [];
        foreach (['frente', 'espalda'] as $orientation) {
            $path = $model->p11ImagePath($patientId, $orientation);
            $bytes = $path && is_file($path) ? (string)file_get_contents($path) : '';
            $images[$orientation] = ['available' => $bytes !== '', 'sha256' => $bytes === '' ? null : hash('sha256', $bytes)];
        }
        return ['images' => $images, 'version' => hash('sha256', json_encode($images, JSON_UNESCAPED_UNICODE))];
    }

    public function capture(int $patientId, string $orientation, string $dataUri, ?string $expectedVersion): array
    {
        if ($patientId < 1 || !in_array($orientation, ['frente', 'espalda'], true)) return ['resultado' => 422, 'mensaje' => 'Mapa M09 no válido.'];
        $db = $this->connection();
        $lockName = 'clinica_m09_map_' . $patientId . '_' . $orientation;
        $lock = $db->prepare('SELECT GET_LOCK(:name, 5)');
        $lock->execute([':name' => $lockName]);
        if ((int)$lock->fetchColumn() !== 1) return ['resultado' => 409, 'mensaje' => 'El mapa está siendo modificado. Inténtalo de nuevo.'];
        try {
            $policy = (new ClinicalWriteEligibilityPolicy($db))->evaluate($patientId, 9);
            if (($policy['finalized'] ?? true) || !($policy['editable'] ?? false)) return ['resultado' => 403, 'mensaje' => 'El módulo M09 no admite cambios porque está finalizado.'];
            $current = $this->read($patientId);
            if ($expectedVersion !== null && $expectedVersion !== '' && !hash_equals((string)$current['version'], $expectedVersion)) return ['resultado' => 409, 'mensaje' => 'El mapa cambió mientras se revisaba. Recarga antes de confirmar.'];
            $result = $this->imageModel()->p11GuardarImagen($patientId, $orientation, $dataUri);
            if ((int)($result['resultado'] ?? 422) !== 200) return $result;
            $after = $this->read($patientId);
            if (empty($after['images'][$orientation]['available'])) return ['resultado' => 500, 'mensaje' => 'No se pudo verificar la imagen guardada.'];
            return ['resultado' => 200, 'mensaje' => 'Mapa M09 guardado.', 'orientation' => $orientation, 'image' => $after['images'][$orientation], 'version' => $after['version']];
        } finally {
            $db->query("SELECT RELEASE_LOCK('" . $lockName . "')");
        }
    }
}
