<?php
namespace App\Services;

final class DeterministicClinicalSummaryService
{
    public function generate(array $snapshot): array
    {
        $sections=[];
        foreach ($snapshot['modules'] as $module) {
            $items=[];
            foreach ($module['records'] as $record) {
                if (($record['record_status'] ?? 'documented') !== 'documented') continue;
                $parts=[];
                foreach ($record['data'] as $key=>$value) if ($value !== null && trim((string)$value)!=='') $parts[]=$key.': '.trim((string)$value);
                if ($parts) $items[]=['text'=>implode('; ', $parts), 'status'=>'documented', 'references'=>[$record['ref']]];
            }
            $sections[]=['key'=>sprintf('M%02d',(int)$module['number']), 'title'=>$module['name'], 'status'=>$module['status'], 'items'=>$items, 'missing'=>$module['status']==='absent'?['No hay registros clínicos persistidos consultables.']:[], 'review'=>[]];
        }
        return ['schema_version'=>'clinical-summary.v1','mode'=>'deterministic','generated_at'=>gmdate('c'),'model'=>null,'coverage'=>['modules_with_records'=>count(array_filter($snapshot['modules'], fn($m)=>$m['status']==='documented')),'modules_total'=>9],'sections'=>$sections,'missing_data'=>array_values(array_filter(array_map(fn($m)=>$m['status']==='absent'?$m['name']:null,$snapshot['modules']))),'review_items'=>[],'notice'=>'Resumen de apoyo sujeto a revisión médica. No sustituye la información original del expediente.'];
    }
}
