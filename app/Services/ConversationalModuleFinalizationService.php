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
    public function __construct(private ?PDO $db = null, private ?M07CanonicalService $m07Canonical = null) {}

    private function connection(): PDO
    {
        return $this->db ?: Database::getInstance();
    }

    /**
     * Finalize a module only after its adapter read model is complete.
     * A 200/not-ready result is intentionally non-final: the caller may keep
     * collecting answers without advancing the module.
     */
    public function finalizeIfComplete(int $patientId, int $moduleId, bool $allowEmptyM06 = false): array
    {
        if ($patientId < 1 || $moduleId < 1 || $moduleId > 9) {
            return ['resultado' => 422, 'mensaje' => 'Datos de finalización no válidos.', 'finalized' => false];
        }
        if ($moduleId === 1) {
            return ['resultado' => 200, 'mensaje' => 'M01 es de solo lectura.', 'finalized' => false, 'finalization_attempted' => false, 'readback' => true, 'code' => 'READ_ONLY_MODULE'];
        }
        if ($moduleId === 7) {
            try {
                return (new ConversationalM07Adapter($this->connection(), $this->m07Canonical))->finalize($patientId, null);
            } catch (\Throwable) {
                return ['resultado' => 503, 'mensaje' => 'No fue posible verificar el estado canónico de M07.', 'finalized' => false, 'finalization_attempted' => false, 'readback' => false, 'code' => 'M07_STATE_UNAVAILABLE'];
            }
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
        if (!$this->isComplete($moduleId, $state, $allowEmptyM06)) {
            $pendingFields = $moduleId === 6 ? $this->m06PendingFields($state) : [];
            $message = $pendingFields !== []
                ? 'M06 no puede finalizar todavía. Completa: ' . implode(', ', $pendingFields) . '.'
                : 'El módulo conserva respuestas pendientes.';
            return ['resultado' => 200, 'mensaje' => $message, 'finalized' => false, 'finalization_attempted' => false, 'readback' => false, 'code' => 'MODULE_NOT_READY_TO_FINALIZE', 'pending_fields' => $pendingFields];
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

    private function isComplete(int $moduleId, array $state, bool $allowEmptyM06 = false): bool
    {
        if (($state['finalized'] ?? false) === true) return true;
        return match ($moduleId) {
            2 => $this->m02Complete($state),
            3 => $this->m03Complete($state),
            4 => $this->m04Complete($state),
            5 => $this->m05Complete($state),
            6 => $this->m06Complete($state, $allowEmptyM06),
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
        foreach ($questions as $row) {
            // A dependent V1 question skipped by an explicit parent No is
            // complete by contract even though its historical value remains
            // empty. Only eligible questions can block completion.
            if (($row['eligible'] ?? true) !== true) continue;
            if (($row['needs_clarification'] ?? false) === true) return false;
            if (trim((string)($row['respuesta'] ?? '')) === '') return false;
        }
        foreach ($diseases as $row) {
            $response = trim((string)($row['respuesta'] ?? ''));
            if ($response === '') return false;
            $requiresDetails = in_array((string)($row['enfermedad'] ?? ''), ['Diabetes Mellitus', 'Enfermedad pulmonar'], true);
            if ($response === 'Si' && $requiresDetails
                && (trim((string)($row['tipo'] ?? '')) === '' || trim((string)($row['year_diagnostico'] ?? '')) === '')) {
                return false;
            }
        }
        return true;
    }

    private function m06Complete(array $state, bool $allowEmpty = false): bool
    {
        $records = $state['records'] ?? [];
        if ($records === []) return $allowEmpty;
        foreach ($records as $record) {
            if (trim((string)($record['medicamento'] ?? '')) === '') return false;
            if (!empty($record['pending_fields'])) return false;
            foreach (['descripcion', 'tiempo_uso', 'dosis', 'medico'] as $field) {
                $stateValue = $record['field_states'][$field] ?? null;
                if ($stateValue === null && trim((string)($record[$field] ?? '')) === '') return false;
                if ($stateValue !== null && !in_array($stateValue, ['ANSWERED', 'UNKNOWN', 'SKIPPED'], true)) return false;
            }
        }
        return true;
    }

    /** Return only contract field labels; never expose stored clinical values. */
    private function m06PendingFields(array $state): array
    {
        $labels = [
            'medicamento' => 'nombre del medicamento',
            'descripcion' => 'motivo de uso',
            'tiempo_uso' => 'tiempo de uso',
            'dosis' => 'dosis',
            'medico' => 'médico que lo indicó',
        ];
        $pending = [];
        foreach (($state['records'] ?? []) as $record) {
            foreach ($labels as $field => $label) {
                $fieldState = $record['field_states'][$field] ?? null;
                if (($record['pending_fields'] ?? null) !== null) {
                    if (in_array($field, $record['pending_fields'], true)) $pending[$field] = $label;
                } elseif ($fieldState === null && trim((string)($record[$field] ?? '')) === '') {
                    $pending[$field] = $label;
                }
            }
        }
        return array_values($pending);
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
