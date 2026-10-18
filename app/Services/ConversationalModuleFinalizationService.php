<?php

namespace App\Services;

use App\Config\Database;
use App\Models\AntecedentesQuirurgicos;
use App\Models\EvaluacionDolorModel;
use App\Models\MedicacionActualModel;
use App\Models\PacienteModulosModelo;
use App\Models\ProcedimientosDolorModel;
use PDO;

/**
 * Orchestrates conversational closure through the existing traditional
 * finalization methods. It does not introduce a second persisted final state.
 */
final class ConversationalModuleFinalizationService
{
    public function __construct(private ?PDO $db = null) {}

    private function connection(): PDO
    {
        return $this->db ?: Database::getInstance();
    }

    /**
     * Finalize a module only after its adapter read model is complete.
     * A 200/not-ready result is intentionally non-final: the caller may keep
     * collecting answers without advancing the module.
     */
    public function finalizeIfComplete(int $patientId, int $moduleId): array
    {
        if ($patientId < 1 || $moduleId < 1 || $moduleId > 9) {
            return ['resultado' => 422, 'mensaje' => 'Datos de finalización no válidos.', 'finalized' => false];
        }
        if ($moduleId === 1) {
            return ['resultado' => 200, 'mensaje' => 'M01 es de solo lectura.', 'finalized' => false, 'finalization_attempted' => false, 'readback' => true, 'code' => 'READ_ONLY_MODULE'];
        }
        if ($moduleId === 7) {
            return ['resultado' => 409, 'mensaje' => 'La finalización M07 permanece deshabilitada.', 'finalized' => false, 'finalization_attempted' => false, 'code' => 'M07_CANONICAL_DISABLED'];
        }

        try {
            $db = $this->connection();
            $policy = (new ClinicalWriteEligibilityPolicy($db))->evaluate($patientId, $moduleId);
            if (($policy['finalized'] ?? null) === true) {
                return ['resultado' => 200, 'mensaje' => 'El módulo ya estaba finalizado.', 'finalized' => true, 'finalization_attempted' => false, 'readback' => true];
            }
            if (($policy['finalized'] ?? null) !== false) {
                return ['resultado' => 503, 'mensaje' => 'No fue posible comprobar el estado de finalización.', 'finalized' => false, 'finalization_attempted' => false, 'readback' => false, 'code' => 'FINALIZATION_STATE_UNAVAILABLE'];
            }
            $state = $this->readModule($db, $patientId, $moduleId);
        } catch (\Throwable) {
            return ['resultado' => 503, 'mensaje' => 'No fue posible comprobar el estado del módulo.', 'finalized' => false, 'finalization_attempted' => false, 'readback' => false, 'code' => 'MODULE_STATE_UNAVAILABLE'];
        }
        if (!$this->isComplete($moduleId, $state)) {
            return ['resultado' => 200, 'mensaje' => 'El módulo conserva respuestas pendientes.', 'finalized' => false, 'finalization_attempted' => false, 'readback' => false, 'code' => 'MODULE_NOT_READY_TO_FINALIZE'];
        }

        try {
            $result = $this->invokeTraditionalFinalizer($db, $patientId, $moduleId);
        } catch (\Throwable) {
            return ['resultado' => 500, 'mensaje' => 'No fue posible finalizar el módulo. La respuesta permanece guardada y el módulo sigue abierto.', 'finalized' => false, 'finalization_attempted' => true, 'readback' => false, 'code' => 'FINALIZATION_FAILED'];
        }
        if ((int)($result['resultado'] ?? 500) !== 200) {
            return [
                'resultado' => (int)($result['resultado'] ?? 422),
                'mensaje' => (string)($result['mensaje'] ?? 'No fue posible finalizar el módulo.'),
                'finalized' => false,
                'finalization_attempted' => true,
                'readback' => false,
                'code' => 'FINALIZATION_REJECTED',
            ];
        }

        $readback = (new ClinicalWriteEligibilityPolicy($db))->evaluate($patientId, $moduleId);
        if (($readback['finalized'] ?? null) !== true) {
            return ['resultado' => 500, 'mensaje' => 'El servidor no confirmó el cierre del módulo. La respuesta permanece guardada.', 'finalized' => false, 'finalization_attempted' => true, 'readback' => false, 'code' => 'FINALIZATION_READBACK_FAILED'];
        }
        return ['resultado' => 200, 'mensaje' => (string)($result['mensaje'] ?? 'Módulo finalizado.'), 'finalized' => true, 'finalization_attempted' => true, 'readback' => true];
    }

    private function readModule(PDO $db, int $patientId, int $moduleId): array
    {
        return match ($moduleId) {
            2 => (new ConversationalM02Adapter($db))->read($patientId),
            3 => (new ConversationalM03Adapter($db))->read($patientId),
            4 => (new ConversationalM04Adapter($db))->read($patientId),
            5 => (new ConversationalM05Adapter($db))->read($patientId),
            6 => (new ConversationalM06Adapter($db))->read($patientId),
            8 => (new ConversationalM08TreatmentAdapter($db))->read($patientId),
            9 => (new ConversationalM09Adapter($db))->read($patientId),
            default => [],
        };
    }

    private function isComplete(int $moduleId, array $state): bool
    {
        if (($state['finalized'] ?? false) === true) return true;
        return match ($moduleId) {
            2 => $this->m02Complete($state),
            3 => $this->m03Complete($state),
            4 => $this->m04Complete($state),
            5 => $this->m05Complete($state),
            6 => $this->m06Complete($state),
            8 => $this->m08Complete($state),
            9 => $this->m09Complete($state),
            default => false,
        };
    }

    private function m02Complete(array $state): bool
    {
        $records = $state['records'] ?? [];
        if ($records === []) return false;
        foreach ($records as $record) {
            if (($record['status'] ?? 'pending') !== 'documented') return false;
            if (($record['detail'] ?? '') === 'Si' && trim((string)($record['relative'] ?? '')) === '') return false;
            if (($record['disease'] ?? '') === 'Diabetes Mellitus' && ($record['detail'] ?? '') === 'Si' && trim((string)($record['diabetes_type'] ?? '')) === '') return false;
        }
        return true;
    }

    private function m03Complete(array $state): bool
    {
        $questions = $state['questions'] ?? [];
        if ($questions === []) return false;
        foreach ($questions as $question) {
            if (empty($question['eligible'])) continue;
            $value = trim((string)($question['respuesta'] ?? ''));
            $semantic = strtoupper(trim((string)($question['semanticState'] ?? '')));
            if ($value === '' && !in_array($semantic, ['UNKNOWN', 'NOT_REMEMBERED'], true)) return false;
        }
        return true;
    }

    private function m04Complete(array $state): bool
    {
        if (($state['response_state'] ?? '') === 'NEGATIVE_DECLARED' && empty($state['has_recorded_surgeries'])) return true;
        $records = array_filter($state['records'] ?? [], static fn(array $record): bool => trim((string)($record['cirugia'] ?? '')) !== '');
        return $records !== [];
    }

    private function m05Complete(array $state): bool
    {
        $questions = $state['questions'] ?? [];
        $diseases = $state['diseases'] ?? [];
        if ($questions === [] && $diseases === []) return false;
        foreach (array_merge($questions, $diseases) as $row) if (trim((string)($row['respuesta'] ?? '')) === '') return false;
        return true;
    }

    private function m06Complete(array $state): bool
    {
        $records = $state['records'] ?? [];
        if ($records === []) return false;
        foreach ($records as $record) foreach (['descripcion', 'medicamento', 'tiempo_uso', 'dosis', 'medico'] as $field) if (trim((string)($record[$field] ?? '')) === '') return false;
        return true;
    }

    private function m08Complete(array $state): bool
    {
        $items = $state['items'] ?? [];
        return $items !== [] && !in_array('pending', array_column($items, 'status'), true);
    }

    private function m09Complete(array $state): bool
    {
        $evaluation = $state['evaluation'] ?? null;
        if (!is_array($evaluation)) return false;
        if (($evaluation['dolor'] ?? '') === 'No') return true;
        return ($evaluation['dolor'] ?? '') === 'Si'
            && trim((string)($evaluation['tiempo_dolor'] ?? '')) !== ''
            && trim((string)($evaluation['descripcion'] ?? '')) !== ''
            && trim((string)($evaluation['incremento'] ?? '')) !== '';
    }

    private function invokeTraditionalFinalizer(PDO $db, int $patientId, int $moduleId): array
    {
        $data = ['idModulo' => $moduleId, 'idPaciente' => $patientId];
        return match ($moduleId) {
            2, 3, 5 => (new PacienteModulosModelo($db))->finalizarModulos($data),
            4 => (new PacienteModulosModelo($db))->finalizarModuloP06($data),
            6 => (new MedicacionActualModel($db))->finalizarModuloPaciente($data),
            8 => (new ProcedimientosDolorModel($db))->p10FinalizarModulo($patientId),
            9 => (new EvaluacionDolorModel($db))->p11FinalizarModulo($patientId),
            default => ['resultado' => 422, 'mensaje' => 'Módulo no finalizable.'],
        };
    }
}
