<?php
namespace App\Services;

final class ClinicalSummaryService
{
    private const REQUIRED_MODULE_KEYS = ['M01','M02','M03','M04','M05','M06','M07','M08','M09'];

    public function __construct(private ?ClinicalSnapshotService $snapshots=null, private ?DeterministicClinicalSummaryService $deterministic=null, private ?GeminiProvider $gemini=null) {}
    public function generate(int $patientId): array
    {
        if (!ClinicalSummarySecurity::enabled()) throw new \RuntimeException('CLINICAL_SUMMARY_DISABLED');
        $snapshot=($this->snapshots ?: new ClinicalSnapshotService())->build($patientId);
        $result=null; $fallback=null;
        if (ClinicalSummarySecurity::externalEnabled()) { try { $result=($this->gemini ?: new GeminiProvider())->generate($snapshot); $this->validate($result,$snapshot); } catch (\Throwable $e) { $fallback=$e->getMessage(); } }
        if ($result===null) { $result=($this->deterministic ?: new DeterministicClinicalSummaryService())->generate($snapshot); $result['fallback_reason']=$fallback ? preg_replace('/[^A-Z0-9_]+/','_',strtoupper($fallback)) : null; }
        $result['snapshot_schema_version']=$snapshot['schema_version']; $result['generated_at']=gmdate('c');
        return ['summary'=>$result,'snapshot'=>$snapshot];
    }
    private function validate(array &$result,array $snapshot): void
    {
        foreach (['schema_version','mode','sections','missing_data','review_items'] as $key) if (!array_key_exists($key,$result)) throw new \RuntimeException('GEMINI_SCHEMA_INVALID');
        if ($result['schema_version'] !== 'clinical-summary.v1' || !is_string($result['mode']) || !is_array($result['sections']) || !is_array($result['missing_data']) || !is_array($result['review_items'])) throw new \RuntimeException('GEMINI_SCHEMA_INVALID');
        $refs=[]; foreach($snapshot['modules'] as $m) foreach($m['records'] as $r) if (($r['record_status'] ?? 'documented') === 'documented') $refs[$r['ref']]=true;
        $snapshotModules=[]; foreach ($snapshot['modules'] as $m) $snapshotModules[sprintf('M%02d',(int)$m['number'])]=$m;
        $allowed=['documented','absent','incomplete','contradictory'];
        $sections=[];
        foreach ($result['sections'] as $section) {
            $key=$section['key'] ?? null;
            if (!is_array($section) || !is_string($key) || !in_array($key,self::REQUIRED_MODULE_KEYS,true) || isset($sections[$key]) || !is_string($section['title'] ?? null) || !in_array($section['status'] ?? null,$allowed,true) || !is_array($section['items'] ?? null) || !is_array($section['missing'] ?? null) || !is_array($section['review'] ?? null)) throw new \RuntimeException('GEMINI_COVERAGE_INVALID');
            $module=$snapshotModules[$key] ?? null;
            if (!$module) throw new \RuntimeException('GEMINI_COVERAGE_INVALID');
            if ($module['status']==='absent' && !in_array($section['status'],['absent','incomplete'],true)) throw new \RuntimeException('GEMINI_COVERAGE_INVALID');
            if ($module['status']==='incomplete' && !in_array($section['status'],['absent','incomplete'],true)) throw new \RuntimeException('GEMINI_COVERAGE_INVALID');
            if ($module['status']==='documented' && !$section['items']) throw new \RuntimeException('GEMINI_COVERAGE_INVALID');
            foreach ($section['items'] as $item) {
                if (!is_array($item) || !is_string($item['text'] ?? null) || !in_array($item['status'] ?? null,$allowed,true) || !is_array($item['references'] ?? null) || !$item['references']) throw new \RuntimeException('GEMINI_EVIDENCE_MISSING');
                foreach ($item['references'] as $ref) if (!is_string($ref) || !isset($refs[$ref])) throw new \RuntimeException('GEMINI_REFERENCE_INVALID');
            }
            foreach ([$section['missing'],$section['review']] as $items) foreach ($items as $item) if (!is_string($item)) throw new \RuntimeException('GEMINI_SCHEMA_INVALID');
            $sections[$key]=$section;
        }
        if (count($sections)!==count(self::REQUIRED_MODULE_KEYS) || array_diff(self::REQUIRED_MODULE_KEYS,array_keys($sections) )) throw new \RuntimeException('GEMINI_COVERAGE_INVALID');
        $result['sections']=array_map(fn($key)=>$sections[$key],self::REQUIRED_MODULE_KEYS);
        foreach ([$result['missing_data'],$result['review_items']] as $items) foreach ($items as $item) if (!is_string($item)) throw new \RuntimeException('GEMINI_SCHEMA_INVALID');
    }
}
