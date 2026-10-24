<?php

declare(strict_types=1);

namespace App\Services;

/** Narrow interpretation and persistence normalization for M03 question 1 only. */
final class M03TobaccoResponsePolicy
{
    public const QUESTION_ID = 1;
    public const THEME_ID = 1;
    public const MAX_RESPONSE_LENGTH = 2000;

    public const AFFIRMATIVE = 'AFFIRMATIVE';
    public const NEGATIVE = 'NEGATIVE';
    public const UNKNOWN = 'UNKNOWN';
    public const NOT_REMEMBERED = 'NOT_REMEMBERED';
    public const AMBIGUOUS = 'AMBIGUOUS';

    public static function isQuestion(array $question): bool
    {
        return (int)($question['idPregunta'] ?? $question['question_id'] ?? $question['id'] ?? 0) === self::QUESTION_ID
            && (int)($question['idTema'] ?? $question['theme_id'] ?? $question['id_tema'] ?? 0) === self::THEME_ID
            && strtolower(trim((string)($question['tipo'] ?? $question['type'] ?? ''))) === 'select';
    }

    public static function classify(string $response): string
    {
        $text = trim((string)preg_replace('/\s+/u', ' ', $response));
        if ($text === '' || mb_strlen($text, 'UTF-8') > self::MAX_RESPONSE_LENGTH) return self::AMBIGUOUS;

        $normalized = mb_strtolower($text, 'UTF-8');
        $plain = trim((string)preg_replace('/[.!?]+$/u', '', $normalized));

        if (preg_match('/^(?:no\s+recuerdo|no\s+me\s+acuerdo)$/u', $plain) === 1) return self::NOT_REMEMBERED;
        if (preg_match('/^(?:no\s+(?:lo\s+)?s[eé]|desconozco)$/u', $plain) === 1) return self::UNKNOWN;

        if (preg_match('/\b(?:no\s+estoy\s+segur[oa]|no\s+tengo\s+claro|tal\s+vez|quiz[aá]s?|a\s+veces|depende|pero|aunque|creo\s+que|s[ií]\s+y\s+no|no\s+y\s+s[ií])\b/u', $normalized) === 1) {
            return self::AMBIGUOUS;
        }

        if (preg_match('/^(?:s[ií])(?:\b|[,;:.!?]|$)/u', $normalized) === 1) {
            $tail = trim((string)preg_replace('/^(?:s[ií])(?:\b|[,;:.!?]|$)\s*/u', '', $normalized));
            if (preg_match('/\b(?:no|nunca|dej[eé]|suspend[ií])\b/u', $tail) === 1) return self::AMBIGUOUS;
            return self::AFFIRMATIVE;
        }

        if (preg_match('/^(?:no|nunca)(?:\b|[,;:.!?]|$)/u', $normalized) === 1) {
            if (preg_match('/\b(?:s[ií]|s[ií] fumo|fumo actualmente)\b/u', $normalized) === 1) return self::AMBIGUOUS;
            if (preg_match('/^(?:no[.!?]*|no\s+(?:fumo|consumo|he\s+fumado|uso|utilizo)\b|no\s*[,;:]\s*(?:no\s+fumo\b|nunca\b|ya\s+no\s+fumo\b|actualmente\s+no\s+fumo\b|gracias\b)|nunca\b)/u', $normalized) === 1) {
                return self::NEGATIVE;
            }
        }

        return self::AMBIGUOUS;
    }

    public static function storedValue(string $response): string
    {
        return match (self::classify($response)) {
            self::AFFIRMATIVE => preg_match('/^s[ií][.!?]*$/iu', trim($response)) === 1 ? 'Si' : trim($response),
            self::NEGATIVE => 'No',
            self::UNKNOWN => 'No lo sé',
            self::NOT_REMEMBERED => 'No recuerdo',
            default => '',
        };
    }

    public static function semanticState(string $decision): ?string
    {
        return match ($decision) {
            self::AFFIRMATIVE => 'KNOWN',
            self::NEGATIVE => 'NEGATIVE',
            self::UNKNOWN => 'UNKNOWN',
            self::NOT_REMEMBERED => 'NOT_REMEMBERED',
            default => null,
        };
    }

    public static function decisionFromSemanticState(string $state): ?string
    {
        return match (strtoupper(trim($state))) {
            'KNOWN' => self::AFFIRMATIVE,
            'NEGATIVE' => self::NEGATIVE,
            'UNKNOWN' => self::UNKNOWN,
            'NOT_REMEMBERED' => self::NOT_REMEMBERED,
            default => null,
        };
    }

    public static function isAffirmative(string $response): bool
    {
        return self::classify($response) === self::AFFIRMATIVE;
    }

    public static function isSimpleAffirmative(string $response): bool
    {
        return preg_match('/^s[ií][.!?]*$/iu', trim($response)) === 1;
    }
}
