<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Services\ClinicalSummaryService;
use App\Services\DeterministicClinicalSummaryService;

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$snapshot = ['schema_version'=>'clinical-snapshot.v1','modules'=>[]];
for ($number = 1; $number <= 9; $number++) {
    $key = sprintf('M%02d', $number);
    $documented = in_array($number, [1, 2, 9], true);
    $snapshot['modules'][] = [
        'number'=>$number,
        'name'=>'Módulo sintético ' . $key,
        'status'=>$documented ? 'documented' : 'absent',
        'records'=>$documented ? [['ref'=>$key . ':R001','data'=>['dato'=>'valor sintético']]] : [],
    ];
}

$section = static function (string $key) use ($snapshot): array {
    $module = $snapshot['modules'][(int)substr($key, 1) - 1];
    $documented = $module['status'] === 'documented';
    return [
        'key'=>$key,
        'title'=>$module['name'],
        'status'=>$module['status'],
        'items'=>$documented ? [['text'=>'Dato clínico sintético','status'=>'documented','references'=>[$key . ':R001']]] : [],
        'missing'=>$documented ? [] : ['Sin registros clínicos persistidos consultables.'],
        'review'=>[],
    ];
};

$valid = [
    'schema_version'=>'clinical-summary.v1',
    'mode'=>'gemini',
    'sections'=>array_map($section, ['M01','M02','M03','M04','M05','M06','M07','M08','M09']),
    'missing_data'=>[],
    'review_items'=>[],
];

$service = new ClinicalSummaryService();
$method = new ReflectionMethod($service, 'validate');
$method->setAccessible(true);
$validate = static function (array &$candidate) use ($method, $service, &$snapshot): ?string {
    try {
        $args = [&$candidate, &$snapshot];
        $method->invokeArgs($service, $args);
        return null;
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }
};

$candidate = $valid;
$assert($validate($candidate) === null, 'valid nine modules should pass');
$assert(array_column($candidate['sections'], 'key') === ['M01','M02','M03','M04','M05','M06','M07','M08','M09'], 'valid modules should be normalized to M01-M09');

$candidate = $valid;
$candidate['sections'] = array_values(array_filter($candidate['sections'], static fn(array $item): bool => $item['key'] !== 'M07' && $item['key'] !== 'M08'));
$assert($validate($candidate) === 'GEMINI_COVERAGE_INVALID', 'seven modules should be rejected');

$candidate = $valid;
$candidate['sections'] = array_values(array_filter($candidate['sections'], static fn(array $item): bool => $item['key'] !== 'M08'));
$assert($validate($candidate) === 'GEMINI_COVERAGE_INVALID', 'eight modules should be rejected');

$candidate = $valid;
$candidate['sections'][8] = $candidate['sections'][0];
$assert($validate($candidate) === 'GEMINI_COVERAGE_INVALID', 'duplicate module should be rejected');

$candidate = $valid;
$candidate['sections'][8]['key'] = 'M99';
$assert($validate($candidate) === 'GEMINI_COVERAGE_INVALID', 'unknown module should be rejected');

$candidate = $valid;
$candidate['sections'] = array_reverse($candidate['sections']);
$assert($validate($candidate) === null && array_column($candidate['sections'], 'key') === ['M01','M02','M03','M04','M05','M06','M07','M08','M09'], 'valid out-of-order modules should be normalized');

$candidate = $valid;
$candidate['sections'][0]['items'] = [];
$assert($validate($candidate) === 'GEMINI_COVERAGE_INVALID', 'documented module without items should be rejected');

$candidate = $valid;
$candidate['sections'][0]['items'][0]['references'] = ['M01:R999'];
$assert($validate($candidate) === 'GEMINI_REFERENCE_INVALID', 'unknown reference should be rejected');

$fallback = (new DeterministicClinicalSummaryService())->generate($snapshot);
$assert($fallback['mode'] === 'deterministic', 'deterministic fallback mode should be explicit');
$assert(count($fallback['sections']) === 9 && array_column($fallback['sections'], 'key') === ['M01','M02','M03','M04','M05','M06','M07','M08','M09'], 'deterministic fallback should contain all nine modules');

if ($failures) {
    fwrite(STDERR, "Clinical summary coverage contract: FAIL\n" . implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "Clinical summary coverage contract: PASS (" . (9 + 2) . " assertions)\n");
