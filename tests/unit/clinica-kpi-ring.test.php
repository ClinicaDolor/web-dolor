<?php

declare(strict_types=1);

/**
 * Pure presentation checks for the D41 progress ring.
 * The production value remains the canonical backend value; only the visual
 * arc is clamped to the supported 0–100% range.
 */
$clampProgress = static function ($value): ?float {
    if ($value === null || $value === '' || !is_numeric($value)) {
        return null;
    }

    return max(0.0, min(100.0, (float) $value));
};

$cases = [
    ['value' => 0, 'expected' => 0.0],
    ['value' => 25, 'expected' => 25.0],
    ['value' => 67.55, 'expected' => 67.55],
    ['value' => 100, 'expected' => 100.0],
    ['value' => null, 'expected' => null],
    ['value' => '', 'expected' => null],
    ['value' => 'not-a-number', 'expected' => null],
];

foreach ($cases as $case) {
    $actual = $clampProgress($case['value']);
    if ($actual !== $case['expected']) {
        throw new RuntimeException(sprintf(
            'Ring case %s expected %s, got %s.',
            var_export($case['value'], true),
            var_export($case['expected'], true),
            var_export($actual, true)
        ));
    }
}

$circumference = 2 * pi() * 25;
$dash = $circumference * ($clampProgress(67.55) / 100);
if ($dash <= 0 || $dash >= $circumference) {
    throw new RuntimeException('The partial progress ring must produce a partial dash.');
}

echo "D41 ring presentation checks: PASS\n";
