<?php

namespace App\Services;

use App\Config\Database;
use PDO;

/**
 * Confirmation-first conversational adapter for the existing P09/M07 contract.
 *
 * The adapter deliberately does not initialize blank response rows while
 * reading.  A row is created only when a confirmed answer is persisted through
 * the canonical MedicacionDolorModel contract.
 */
final class ConversationalM07Adapter
{
    public function __construct(private ?PDO $db = null) {}

    private function connection(): PDO
    {
        return $this->db ?: Database::getInstance();
    }

    /**
     * Read the P09 catalogue grouped by its persisted themes, preserving the
     * catalogue identifiers and labels.  No response row is created here.
     */
    public function read(int $patientId, ?int $clinicId = null, string $role = 'patient'): array
    {
        if ($patientId < 1) throw new \InvalidArgumentException('PATIENT_INVALID');
        $canonical = $this->readCanonical($patientId, $clinicId, $role);
        if ($canonical !== null) return $canonical;
        return $this->readLegacy($patientId);
    }

    /**
     * The canonical service is the source of truth whenever its schema is
     * present. A legacy-only database keeps the historical read projection so
     * existing environments remain compatible; this method never writes.
     */
    private function readCanonical(int $patientId, ?int $clinicId, string $role): ?array
    {
        $db = $this->connection();
        $clinicId = $clinicId ?: $this->resolvePatientClinic($db, $patientId);
        if ($clinicId === null) return null;
        try {
            $state = (new M07CanonicalService($db))->read($patientId, $clinicId, strtolower(trim($role)) ?: 'patient');
        } catch (M07CanonicalException $exception) {
            if ($exception->codeName === 'M07_SCHEMA_UNAVAILABLE') return null;
            throw $exception;
        }

        $categories = [];
        $pending = 0;
        $conflicts = 0;
        foreach ($state['records'] ?? [] as $record) {
            $themeId = (int)($record['theme_id'] ?? 0);
            $questionId = (int)($record['question_id'] ?? 0);
            if (!isset($categories[$themeId])) {
                $categories[$themeId] = [
                    'theme_id' => $themeId,
                    'theme' => (string)($record['legacy_projection']['theme'] ?? 'M07'),
                    'items' => [],
                ];
            }
            $status = (string)($record['status'] ?? 'pending');
            if ($status !== 'complete') $pending++;
            if ($status === 'conflict') $conflicts++;
            $categories[$themeId]['items'][] = [
                'question_id' => $questionId,
                'question' => (string)($record['name'] ?? ''),
                'response_id' => (int)($record['id'] ?? 0),
                'fields' => [
                    'respuesta' => trim((string)($record['fields']['respuesta'] ?? '')),
                    'dosis' => trim((string)($record['fields']['dosis'] ?? '')),
                    'resultados' => trim((string)($record['fields']['resultados'] ?? '')),
                    'consumo' => trim((string)($record['fields']['consumo'] ?? '')),
                    'descripcion' => trim((string)($record['fields']['descripcion'] ?? '')),
                ],
                'duplicate_count' => ($record['pending_reason'] ?? '') === 'DUPLICATE_RESPONSE' ? 2 : 0,
                'status' => $status,
                'pending_reason' => $record['pending_reason'] ?? null,
                'canonical_states' => $record['states'] ?? [],
                'efficacy' => $record['efficacy'] ?? [],
                'tolerability' => $record['tolerability'] ?? [],
                'source' => $record['source'] ?? 'CATALOG',
                'version' => (int)($record['version'] ?? 0),
                'other_blocked' => false,
            ];
        }
        foreach ($state['other_medications'] ?? [] as $other) {
            if (($other['status'] ?? '') === 'pending') $pending++;
        }

        return [
            'key' => 'M07',
            'title' => 'Medicamentos utilizados para el dolor',
            'categories' => array_values($categories),
            'records' => $state['records'] ?? [],
            'other_medications' => $state['other_medications'] ?? [],
            'pending_items' => $pending,
            'conflicts' => $conflicts,
            'version' => (string)($state['version'] ?? ''),
            'finalized' => false,
            'write_enabled' => false,
            'write_blocker' => 'M07_CANONICAL_DISABLED',
            'finalization_enabled' => false,
            'policy_source' => 'M07CanonicalService',
            'canonical_read' => true,
            'clinic_id' => $clinicId,
            'role' => strtolower(trim($role)) ?: 'patient',
        ];
    }

    private function resolvePatientClinic(PDO $db, int $patientId): ?int
    {
        try {
            $statement = $db->prepare('SELECT id_clinica FROM pc_paciente WHERE id = :patient LIMIT 1');
            $statement->execute([':patient' => $patientId]);
            $clinic = $statement->fetchColumn();
            return $clinic === false || (int)$clinic < 1 ? null : (int)$clinic;
        } catch (\Throwable) {
            return null;
        }
    }

    private function readLegacy(int $patientId): array
    {
        $db = $this->connection();
        $stmt = $db->prepare(
            'SELECT t.id AS theme_id, t.tema AS theme, q.id AS question_id,
                    q.pregunta AS question, r.id AS response_id,
                    COALESCE(r.respuesta, \'\') AS respuesta,
                    COALESCE(r.dosis, \'\') AS dosis,
                    COALESCE(r.resultados, \'\') AS resultados,
                    COALESCE(r.consumo, \'\') AS consumo,
                    COALESCE(r.descripcion, \'\') AS descripcion
             FROM pac_temas_modulo_7 t
             INNER JOIN pac_preguntas_modulo_7 q ON q.id_tema = t.id
             LEFT JOIN pac_respuestas_paciente_modulo_7 r
               ON r.id_pregunta = q.id AND r.id_paciente = :patient
             ORDER BY t.id ASC, q.id ASC, r.id ASC'
        );
        $stmt->execute([':patient' => $patientId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $categories = [];
        foreach ($rows as $row) {
            $themeId = (int)$row['theme_id'];
            $questionId = (int)$row['question_id'];
            if (!isset($categories[$themeId])) {
                $categories[$themeId] = [
                    'theme_id' => $themeId,
                    'theme' => (string)$row['theme'],
                    'items' => [],
                ];
            }
            if (!isset($categories[$themeId]['items'][$questionId])) {
                $categories[$themeId]['items'][$questionId] = [
                    'question_id' => $questionId,
                    'question' => (string)$row['question'],
                    'response_id' => (int)($row['response_id'] ?? 0),
                    'fields' => [
                        'respuesta' => trim((string)$row['respuesta']),
                        'dosis' => trim((string)$row['dosis']),
                        'resultados' => trim((string)$row['resultados']),
                        'consumo' => trim((string)$row['consumo']),
                        'descripcion' => trim((string)$row['descripcion']),
                    ],
                    'response_ids' => [],
                ];
            }
            if ((int)($row['response_id'] ?? 0) > 0) {
                $categories[$themeId]['items'][$questionId]['response_ids'][] = (int)$row['response_id'];
            }
        }

        $pending = 0;
        $conflicts = 0;
        foreach ($categories as &$category) {
            $category['items'] = array_values($category['items']);
            foreach ($category['items'] as &$item) {
                $item['duplicate_count'] = count($item['response_ids']) > 1 ? count($item['response_ids']) : 0;
                $item['status'] = $this->statusFor($item);
                $item['other_blocked'] = $this->isOtherMedication((string)$item['question']);
                if ($item['other_blocked'] && $item['fields']['respuesta'] === 'Si') {
                    $item['status'] = 'blocked';
                    $item['blocked_reason'] = 'OTHER_MEDICATION_NAME_UNREPRESENTABLE';
                }
                if (in_array($item['status'], ['pending', 'blocked'], true)) $pending++;
                if (in_array($item['status'], ['conflict', 'duplicate'], true)) $conflicts++;
                unset($item['response_ids']);
            }
            unset($item);
        }
        unset($category);

        $categories = array_values($categories);
        $policy = (new ClinicalWriteEligibilityPolicy($db))->evaluate($patientId, 7);
        $versionPayload = ['categories' => $categories, 'policy' => $policy];
        return [
            'key' => 'M07',
            'title' => 'Medicamentos utilizados para el dolor',
            'categories' => $categories,
            'pending_items' => $pending,
            'conflicts' => $conflicts,
            'version' => hash('sha256', json_encode($versionPayload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            'finalized' => (bool)($policy['finalized'] ?? false),
            'write_enabled' => (bool)($policy['editable'] ?? false),
            'write_blocker' => $policy['reason'] ?? null,
            'finalization_enabled' => false,
            'policy_source' => $policy['source'] ?? 'pac_historia_clinica_finalizar',
        ];
    }

    /**
     * Prepare a structured proposal without writing.  Unknown and combined
     * outcomes remain pending instead of being reduced to a false negative.
     */
    public function interpret(int $questionId, string $field, string $utterance): array
    {
        if ($questionId < 1 || trim($field) === '' || trim($utterance) === '') {
            throw new \InvalidArgumentException('Indica una respuesta M07.');
        }
        $question = $this->question($questionId);
        if (!$question) throw new \DomainException('La pregunta M07 no pertenece al catálogo autorizado.');
        $field = $this->normalizeField($field);
        $text = trim($utterance);
        $normalized = mb_strtolower($text, 'UTF-8');

        if (preg_match('/\b(no\s+recuerdo|no\s+sé|no\s+se|desconozco|no\s+estoy\s+segur[oa])\b/u', $normalized)) {
            return $this->pendingProposal($questionId, $field, 'No se registró nada. La respuesta queda pendiente porque indicaste que no lo recuerdas.');
        }

        if ($field === 'respuesta') {
            $yes = preg_match('/\b(s[ií]|sí|si|afirmativo|lo\s+utilic[eé])\b/u', $normalized) === 1;
            $no = preg_match('/\b(no|nunca|jam[aá]s|negativo)\b/u', $normalized) === 1;
            if ($yes && $no) return $this->pendingProposal($questionId, $field, 'La respuesta contiene señales contradictorias. Revisa el medicamento y elige Sí o No.', 'M07_AMBIGUOUS_RESPONSE');
            if (!$yes && !$no) return $this->pendingProposal($questionId, $field, 'Indica Sí o No para este medicamento.', 'M07_RESPONSE_REQUIRES_YES_NO');
            return $this->proposal($questionId, $field, $yes ? 'Si' : 'No', $text);
        }

        if ($field === 'resultados') {
            $adverse = preg_match('/\b(efectos?\s+adversos?|reacciones?|me\s+hizo\s+daño)\b/u', $normalized) === 1;
            $effective = preg_match('/\b(funcion[oó]|eficaz|me\s+ayud[oó])\b/u', $normalized) === 1;
            $ineffective = preg_match('/\b(no\s+funcion[oó]|no\s+me\s+ayud[oó]|ineficaz)\b/u', $normalized) === 1;
            if ($adverse && ($effective || $ineffective)) return $this->pendingProposal($questionId, $field, 'La respuesta combina eficacia y efectos adversos. No se puede reducir a una sola opción T3 sin revisión clínica.', 'M07_COMBINED_OUTCOME_REVIEW');
            if ($adverse) return $this->proposal($questionId, $field, 'Tuve efectos adversos', $text);
            if ($ineffective) return $this->proposal($questionId, $field, 'No funciono', $text);
            if ($effective) return $this->proposal($questionId, $field, 'Funciono', $text);
            return $this->pendingProposal($questionId, $field, 'El resultado no coincide con las opciones oficiales de M07.', 'M07_RESULT_REQUIRES_OFFICIAL_OPTION');
        }

        if ($field === 'consumo') {
            $yes = preg_match('/\b(s[ií]|sí|si|todav[ií]a|contin[uú]o)\b/u', $normalized) === 1;
            $no = preg_match('/\b(no|ya\s+no|dej[eé])\b/u', $normalized) === 1;
            if ($yes === $no) return $this->pendingProposal($questionId, $field, 'Indica si todavía utilizas este medicamento: Sí o No.', 'M07_CONSUMPTION_REQUIRES_YES_NO');
            return $this->proposal($questionId, $field, $yes ? 'Si' : 'No', $text);
        }

        return $this->proposal($questionId, $field, $text, $text);
    }

    public function capture(int $patientId, array $item, ?string $expectedVersion): array
    {
        return $this->captureBatch($patientId, [$item], $expectedVersion);
    }

    /**
     * Persist one or more explicitly confirmed medication answers atomically.
     * The canonical model remains responsible for field validation and SQL;
     * this adapter supplies the transaction, version guard and dependency guard.
     */
    public function captureBatch(int $patientId, array $items, ?string $expectedVersion): array
    {
        if ($patientId < 1 || $items === [] || $expectedVersion === null || trim($expectedVersion) === '') {
            return ['resultado' => 422, 'code' => 'M07_VERSION_REQUIRED', 'mensaje' => 'La confirmación M07 requiere una versión vigente.'];
        }
        $db = $this->connection();
        $lockName = 'clinica_m07_capture_' . $patientId;
        $lock = $db->prepare('SELECT GET_LOCK(:name, 5)');
        $lock->execute([':name' => $lockName]);
        if ((int)$lock->fetchColumn() !== 1) return ['resultado' => 409, 'code' => 'M07_CAPTURE_BUSY', 'mensaje' => 'M07 está siendo actualizado. Inténtalo de nuevo.'];
        try {
            $state = $this->read($patientId);
            if (!$state['write_enabled']) return ['resultado' => 409, 'code' => 'M07_WRITE_BLOCKED', 'mensaje' => 'M07 no está disponible para edición.'];
            if (!hash_equals((string)$state['version'], (string)$expectedVersion)) return ['resultado' => 409, 'code' => 'M07_VERSION_CONFLICT', 'mensaje' => 'M07 cambió mientras se revisaba. Recarga antes de confirmar.'];
            $prepared = $this->validateBatch($state, $items);
            if ($prepared['resultado'] !== 200) return $prepared;

            $db->beginTransaction();
            $model = new \App\Models\MedicacionDolorModel($db);
            foreach ($prepared['items'] as $item) {
                $responseId = (int)($item['response_id'] ?? 0);
                $fields = $item['fields'];
                $operations = [['type' => 1, 'detail' => $fields['respuesta']]];
                if ($fields['respuesta'] === 'Si') {
                    $operations[] = ['type' => 2, 'detail' => $fields['dosis']];
                    $operations[] = ['type' => 3, 'detail' => $fields['resultados']];
                    $operations[] = ['type' => 4, 'detail' => $fields['consumo']];
                    if ($fields['resultados'] === 'Tuve efectos adversos') $operations[] = ['type' => 5, 'detail' => $fields['descripcion']];
                }
                foreach ($operations as $operation) {
                    $result = $model->editarCuestionarioM7Paciente([
                        'idPaciente' => $patientId,
                        'idRespuesta' => $responseId,
                        'idPregunta' => $item['question_id'],
                        'idTema' => $item['theme_id'],
                        'idTipo' => $operation['type'],
                        'detalle' => $operation['detail'],
                    ]);
                    if ((int)($result['resultado'] ?? 0) !== 200) throw new \RuntimeException('M07 canonical persistence rejected an operation.');
                    $responseId = (int)($result['idRespuesta'] ?? $responseId);
                }
            }
            $db->commit();
            $after = $this->read($patientId);
            return [
                'resultado' => 200,
                'code' => 'M07_CONFIRMED',
                'mensaje' => 'Respuesta M07 confirmada y guardada.',
                'readback' => $after,
                'version' => $after['version'],
                'pending_items' => $after['pending_items'],
            ];
        } catch (\Throwable) {
            if ($db->inTransaction()) $db->rollBack();
            return ['resultado' => 422, 'code' => 'M07_ATOMIC_ROLLBACK', 'mensaje' => 'No fue posible guardar M07; no se aplicaron cambios parciales.'];
        } finally {
            $release = $db->prepare('SELECT RELEASE_LOCK(:name)');
            $release->execute([':name' => $lockName]);
        }
    }

    private function validateBatch(array $state, array $items): array
    {
        $lookup = [];
        foreach ($state['categories'] as $category) foreach ($category['items'] as $item) {
            $lookup[(int)$item['question_id']] = ['theme_id' => (int)$category['theme_id'], 'item' => $item];
        }
        $prepared = [];
        $seenQuestions = [];
        foreach ($items as $raw) {
            if (!is_array($raw)) return ['resultado' => 422, 'code' => 'M07_ITEM_INVALID', 'mensaje' => 'Una respuesta M07 no es válida.'];
            $questionId = (int)($raw['question_id'] ?? $raw['idPregunta'] ?? 0);
            $themeId = (int)($raw['theme_id'] ?? $raw['idTema'] ?? 0);
            if (isset($seenQuestions[$questionId])) return ['resultado' => 409, 'code' => 'M07_DUPLICATE_ITEM', 'mensaje' => 'La misma pregunta M07 no puede enviarse dos veces en una confirmación.'];
            $seenQuestions[$questionId] = true;
            $entry = $lookup[$questionId] ?? null;
            if (!$entry || $entry['theme_id'] !== $themeId) return ['resultado' => 403, 'code' => 'M07_SCOPE_DENIED', 'mensaje' => 'La pregunta M07 no pertenece al tema autorizado.'];
            if (($entry['item']['duplicate_count'] ?? 0) > 1) return ['resultado' => 409, 'code' => 'M07_DUPLICATE_RESPONSE', 'mensaje' => 'M07 tiene respuestas duplicadas; no se modificó el expediente.'];
            if (($entry['item']['status'] ?? '') === 'blocked') return ['resultado' => 422, 'code' => $entry['item']['blocked_reason'] ?? 'M07_BLOCKED', 'mensaje' => 'Este medicamento requiere una decisión clínica antes de registrarse.'];
            $fields = $raw['fields'] ?? $raw;
            if (!is_array($fields)) return ['resultado' => 422, 'code' => 'M07_FIELDS_INVALID', 'mensaje' => 'Los campos M07 no son válidos.'];
            $response = trim((string)($fields['respuesta'] ?? $fields['response'] ?? ''));
            if (!in_array($response, ['Si', 'No'], true)) return ['resultado' => 422, 'code' => 'M07_RESPONSE_INVALID', 'mensaje' => 'La respuesta M07 debe ser Sí o No.'];
            $normalized = [
                'respuesta' => $response,
                'dosis' => trim((string)($fields['dosis'] ?? '')),
                'resultados' => trim((string)($fields['resultados'] ?? $fields['resultado'] ?? '')),
                'consumo' => trim((string)($fields['consumo'] ?? '')),
                'descripcion' => trim((string)($fields['descripcion'] ?? '')),
            ];
            $current = $entry['item']['fields'];
            $hasDependents = $current['dosis'] !== '' || $current['resultados'] !== '' || $current['consumo'] !== '' || $current['descripcion'] !== '';
            $confirmClear = filter_var($raw['confirm_clear'] ?? $raw['confirmClear'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($response === 'No') {
                if ($hasDependents && !$confirmClear) return ['resultado' => 409, 'code' => 'M07_DEPENDENT_CLEAR_CONFIRMATION_REQUIRED', 'mensaje' => 'Este cambio eliminaría datos dependientes; confirma explícitamente para continuar.'];
                $normalized['dosis'] = $normalized['resultados'] = $normalized['consumo'] = $normalized['descripcion'] = '';
            } else {
                if ($entry['item']['other_blocked']) return ['resultado' => 422, 'code' => 'OTHER_MEDICATION_NAME_UNREPRESENTABLE', 'mensaje' => '“Otro” requiere una decisión clínica sobre dónde guardar su nombre.'];
                if ($normalized['dosis'] === '' || !in_array($normalized['resultados'], ['Funciono', 'No funciono', 'Tuve efectos adversos'], true) || !in_array($normalized['consumo'], ['Si', 'No'], true)) return ['resultado' => 422, 'code' => 'M07_DEPENDENCIES_INCOMPLETE', 'mensaje' => 'Completa dosis, resultado y consumo antes de confirmar.'];
                if ($normalized['resultados'] === 'Tuve efectos adversos' && $normalized['descripcion'] === '') return ['resultado' => 422, 'code' => 'M07_ADVERSE_DESCRIPTION_REQUIRED', 'mensaje' => 'Describe los efectos adversos antes de confirmar.'];
                if ($current['resultados'] === 'Tuve efectos adversos' && $normalized['resultados'] !== 'Tuve efectos adversos' && !$confirmClear) return ['resultado' => 409, 'code' => 'M07_ADVERSE_CLEAR_CONFIRMATION_REQUIRED', 'mensaje' => 'El cambio eliminaría la descripción de efectos adversos; confirma explícitamente.'];
            }
            $prepared[] = ['question_id' => $questionId, 'theme_id' => $themeId, 'response_id' => (int)$entry['item']['response_id'], 'fields' => $normalized];
        }
        return ['resultado' => 200, 'items' => $prepared];
    }

    private function question(int $questionId): ?array
    {
        $stmt = $this->connection()->prepare('SELECT id, id_tema, pregunta FROM pac_preguntas_modulo_7 WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $questionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function statusFor(array $item): string
    {
        if (($item['duplicate_count'] ?? 0) > 1) return 'duplicate';
        $f = $item['fields'];
        if ($f['respuesta'] === '') return 'pending';
        if ($f['respuesta'] === 'No') return ($f['dosis'] . $f['resultados'] . $f['consumo'] . $f['descripcion']) === '' ? 'complete' : 'conflict';
        if ($f['respuesta'] !== 'Si') return 'conflict';
        if ($f['dosis'] === '' || !in_array($f['resultados'], ['Funciono', 'No funciono', 'Tuve efectos adversos'], true) || !in_array($f['consumo'], ['Si', 'No'], true)) return 'pending';
        if ($f['resultados'] === 'Tuve efectos adversos' && $f['descripcion'] === '') return 'pending';
        return 'complete';
    }

    private function isOtherMedication(string $question): bool
    {
        return preg_match('/\b(otro|otros|otra|otras)\b/iu', $question) === 1;
    }

    private function normalizeField(string $field): string
    {
        $map = ['response' => 'respuesta', 'respuesta' => 'respuesta', 'dose' => 'dosis', 'dosis' => 'dosis', 'result' => 'resultados', 'resultado' => 'resultados', 'resultados' => 'resultados', 'consumption' => 'consumo', 'consumo' => 'consumo', 'description' => 'descripcion', 'descripcion' => 'descripcion'];
        return $map[mb_strtolower(trim($field), 'UTF-8')] ?? trim($field);
    }

    private function proposal(int $questionId, string $field, string $value, string $source): array
    {
        return ['mode' => 'deterministic', 'kind' => 'm07_proposal', 'target' => 'm07', 'question_id' => $questionId, 'field' => $field, 'structured_value' => $value, 'source_text' => $source, 'requires_confirmation' => true, 'can_confirm' => true, 'write_enabled' => true];
    }

    private function pendingProposal(int $questionId, string $field, string $message, string $code = 'M07_UNKNOWN_PENDING'): array
    {
        return ['mode' => 'deterministic', 'kind' => 'm07_pending', 'target' => 'm07', 'question_id' => $questionId, 'field' => $field, 'structured_value' => null, 'clarification' => $message, 'code' => $code, 'requires_confirmation' => false, 'can_confirm' => false, 'write_enabled' => false];
    }
}
