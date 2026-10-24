<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AntecedentesNoPatologicosModel;

/** Pure presentation classification over the authenticated patient's adapter projections. */
final class PreviousResponsesStatusClassifier
{
    private const PENDING_STATUSES = ['PENDIENTE', 'POR_ACLARAR', 'REQUIERE_REVISION'];
    private const SEMANTIC_STATES = ['KNOWN', 'NEGATIVE', 'UNKNOWN', 'NOT_REMEMBERED'];

    public static function m02(array $question, ?array $record, array $moduleState): array
    {
        $disease = (string)($question['question_id'] ?? '');
        $response = is_array($question['response'] ?? null) ? $question['response'] : [];
        $eligibility = is_array($question['eligibility'] ?? null) ? $question['eligibility'] : [];
        $responsePersisted = ($response['persisted'] ?? false) === true;
        $recordId = (int)($record['id'] ?? 0);

        if (in_array($disease, $moduleState['duplicate_diseases'] ?? [], true)) {
            return self::result('REQUIERE_REVISION', 'Se detectaron respuestas duplicadas; se requiere aclarar cuál es la vigente.');
        }
        if ($responsePersisted && $recordId > 0) {
            $semantic = strtoupper(trim((string)($response['semantic_state'] ?? $record['semantic_state'] ?? '')));
            if ($semantic !== '' && !in_array($semantic, self::SEMANTIC_STATES, true)) {
                return self::result('REQUIERE_REVISION', 'El adaptador no reconoce el estado de esta respuesta.');
            }
            if (in_array($semantic, ['UNKNOWN', 'NOT_REMEMBERED'], true)) {
                return self::result('POR_ACLARAR');
            }
            // M02 currently has no semantic UNKNOWN projection. Never infer it from free text.
            if ($semantic === '' && in_array(trim((string)($record['detail'] ?? '')), ['No lo sé', 'No recuerdo'], true)) {
                return self::result('REQUIERE_REVISION', 'El texto no está acompañado por un estado semántico verificable.');
            }
            return self::result('REGISTRADA');
        }
        if ($recordId > 0 || $responsePersisted) {
            return self::result('REQUIERE_REVISION', 'El registro y el estado de respuesta no coinciden.');
        }
        if (!empty($moduleState['finalized'])) {
            return self::result('REQUIERE_REVISION', 'El módulo aparece finalizado aunque esta pregunta no tiene una respuesta registrada.');
        }
        if (($eligibility['eligible'] ?? null) === true || ($eligibility['eligible'] ?? null) === 1 || ($eligibility['eligible'] ?? null) === '1') {
            return self::result('PENDIENTE');
        }
        if (($eligibility['eligible'] ?? null) === false || ($eligibility['eligible'] ?? null) === 0 || ($eligibility['eligible'] ?? null) === '0') {
            if (($eligibility['state'] ?? null) === 'not_applicable') {
                return self::result('NO_APLICA', 'No requiere respuesta según la elegibilidad declarada por el adaptador.');
            }
        }
        return self::result('REQUIERE_REVISION', 'El adaptador no proporciona una elegibilidad confiable para esta pregunta.');
    }

    /** @param list<array<string,mixed>> $rows All adapter rows for one canonical question ID. */
    public static function m03(array $rows, array $questionsById, bool $semanticStateAvailable, bool $finalized = false): array
    {
        if (count($rows) !== 1) {
            return self::result('REQUIERE_REVISION', 'Hay varias filas para esta pregunta; no se elige una respuesta vigente.');
        }
        $question = $rows[0];
        $questionId = (int)($question['idPregunta'] ?? 0);
        $answer = trim((string)($question['respuesta'] ?? ''));
        $semantic = strtoupper(trim((string)($question['semanticState'] ?? '')));
        $responseId = (int)($question['idRespuesta'] ?? 0);
        $eligible = self::isTrue($question['eligible'] ?? null);
        $persisted = $responseId > 0
            && ($answer !== '' || in_array($semantic, self::SEMANTIC_STATES, true));

        if ($persisted) {
            if (!$eligible || !self::isPersistedAnswerConsistent($question, $semanticStateAvailable)) {
                return self::result('REQUIERE_REVISION', 'La elegibilidad o el estado guardado requiere verificación.');
            }
            if (in_array($semantic, ['UNKNOWN', 'NOT_REMEMBERED'], true)) {
                return self::result('POR_ACLARAR');
            }
            if (in_array($semantic, ['KNOWN', 'NEGATIVE', 'LEGACY_UNCLASSIFIED'], true)) {
                return self::result('REGISTRADA');
            }
            return self::result('REQUIERE_REVISION', 'El adaptador no proporciona un estado de respuesta confiable.');
        }

        if ($responseId > 0 || $answer !== '' || $semantic !== 'UNANSWERED') {
            return self::result('REQUIERE_REVISION', 'El registro de respuesta no coincide con el estado del catálogo.');
        }
        $parentId = (int)($question['parentQuestionId'] ?? 0);
        if ($parentId < 1 || $parentId === $questionId) {
            if (!$eligible) return self::result('REQUIERE_REVISION', 'El adaptador no confirmó por qué esta pregunta no es elegible.');
            return $finalized
                ? self::result('REQUIERE_REVISION', 'El módulo aparece finalizado aunque esta pregunta elegible no tiene respuesta.')
                : self::result('PENDIENTE');
        }

        $parentRows = $questionsById[$parentId] ?? [];
        if (count($parentRows) !== 1) {
            return self::result('REQUIERE_REVISION', 'No se pudo determinar con seguridad la respuesta principal de este tema.');
        }
        $parent = $parentRows[0];
        $outcome = self::parentOutcome($parent, $semanticStateAvailable);
        if ($outcome === 'NEGATIVE' && !$eligible) {
            return self::result('NO_APLICA', 'No aplica según la respuesta principal registrada para este tema.');
        }
        if ($outcome === 'AFFIRMATIVE' && $eligible) {
            return $finalized
                ? self::result('REQUIERE_REVISION', 'El módulo aparece finalizado aunque esta pregunta elegible no tiene respuesta.')
                : self::result('PENDIENTE');
        }
        return self::result('REQUIERE_REVISION', 'La respuesta principal no permite determinar de forma confiable si esta pregunta aplica.');
    }

    private static function parentOutcome(array $parent, bool $semanticStateAvailable): ?string
    {
        $answer = trim((string)($parent['respuesta'] ?? ''));
        $semantic = strtoupper(trim((string)($parent['semanticState'] ?? '')));
        $responseId = (int)($parent['idRespuesta'] ?? 0);
        $persisted = $responseId > 0 && ($answer !== '' || in_array($semantic, self::SEMANTIC_STATES, true));
        if (!$persisted || !self::isPersistedAnswerConsistent($parent, $semanticStateAvailable)) return null;

        $parentId = (int)($parent['idPregunta'] ?? 0);
        if ($parentId === M03TobaccoResponsePolicy::QUESTION_ID) {
            if (!in_array($semantic, ['KNOWN', 'NEGATIVE', 'LEGACY_UNCLASSIFIED'], true)) return null;
            return match ($parent['navigationDecision'] ?? null) {
                M03TobaccoResponsePolicy::AFFIRMATIVE => 'AFFIRMATIVE',
                M03TobaccoResponsePolicy::NEGATIVE => 'NEGATIVE',
                default => null,
            };
        }
        if (in_array($semantic, ['NEGATIVE', 'LEGACY_UNCLASSIFIED'], true) && $answer === 'No') return 'NEGATIVE';
        if (in_array($semantic, ['KNOWN', 'LEGACY_UNCLASSIFIED'], true) && in_array($answer, ['Si', 'Sí'], true)) return 'AFFIRMATIVE';
        return null;
    }

    private static function isPersistedAnswerConsistent(array $question, bool $semanticStateAvailable): bool
    {
        $questionId = (int)($question['idPregunta'] ?? 0);
        $themeId = (int)($question['idTema'] ?? 0);
        $type = (string)($question['tipo'] ?? '');
        $answer = trim((string)($question['respuesta'] ?? ''));
        $semantic = strtoupper(trim((string)($question['semanticState'] ?? '')));

        if ($semanticStateAvailable && !in_array($semantic, self::SEMANTIC_STATES, true)) return false;
        if (in_array($semantic, ['UNKNOWN', 'NOT_REMEMBERED'], true)) {
            $expected = AntecedentesNoPatologicosModel::canonicalM03UnknownValue($semantic);
            return $semanticStateAvailable ? $answer === '' : $answer === $expected;
        }
        if ($semantic === 'KNOWN' && $answer === '') return false;
        if ($semantic === 'NEGATIVE') {
            if ($type === 'select') return $answer === 'No';
            return $questionId === 5 && $themeId === 1 && $answer === AntecedentesNoPatologicosModel::M03_Q5_NOT_STOPPED_DETAIL;
        }
        if ($semantic === 'LEGACY_UNCLASSIFIED') return $answer !== '';
        if ($semantic === 'KNOWN' && $type === 'select') {
            if ($questionId === M03TobaccoResponsePolicy::QUESTION_ID && $themeId === M03TobaccoResponsePolicy::THEME_ID) {
                return M03TobaccoResponsePolicy::classify($answer) === M03TobaccoResponsePolicy::AFFIRMATIVE;
            }
            return in_array($answer, ['Si', 'Sí'], true);
        }
        return $semantic === 'KNOWN' && $answer !== '';
    }

    private static function isTrue(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }

    private static function result(string $status, string $reason = ''): array
    {
        return [
            'status' => $status,
            'pending' => in_array($status, self::PENDING_STATUSES, true),
            'reason' => $reason,
        ];
    }
}
