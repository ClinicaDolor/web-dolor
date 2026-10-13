<?php
namespace App\Services;

use App\Config\Database;
use PDO;

/** Conversational M06 adapter over the existing current-medication record contract. */
final class ConversationalM06Adapter
{
    public function __construct(private ?PDO $db=null) {}
    private function connection(): PDO { return $this->db ?: Database::getInstance(); }

    public function read(int $patientId): array
    {
        if($patientId<1)throw new \InvalidArgumentException('PATIENT_INVALID');$db=$this->connection();
        $s=$db->prepare('SELECT id,medicamento,descripcion,tiempo_uso,dosis,medico FROM pac_medicacion_actual_modulo_6 WHERE id_paciente=:id ORDER BY id');$s->execute([':id'=>$patientId]);$records=$s->fetchAll(PDO::FETCH_ASSOC);
        $policy=(new ClinicalWriteEligibilityPolicy($db))->evaluate($patientId,6);$pending=null;foreach($records as $r){foreach(['descripcion','medicamento','tiempo_uso','dosis','medico'] as $field)if(trim((string)($r[$field]??''))===''){$pending=$r;break 2;}}
        return ['key'=>'M06','title'=>'Medicación actual','records'=>$records,'pending_record'=>$pending,'version'=>hash('sha256',json_encode($records,JSON_UNESCAPED_UNICODE)),'finalized'=>(bool)($policy['finalized']??true),'write_enabled'=>(bool)($policy['editable']??false),'write_blocker'=>$policy['reason']??null,'policy_source'=>$policy['source']??'pac_historia_clinica_finalizar'];
    }

    public function interpret(string $utterance): array
    {
        $text=trim($utterance);if($text==='')throw new \InvalidArgumentException('Indica los datos de la medicación.');$n=mb_strtolower($text,'UTF-8');
        $fields=['descripcion'=>null,'medicamento'=>null,'tiempo_uso'=>null,'dosis'=>null,'medico'=>null];
        $patterns=['descripcion'=>'/(?:medicamento|tomo|tomando)\s+([^,.;]+?)(?=\s+(?:para|por|durante|a dosis|dosis|me lo recet[oó]|m[eé]dico)\b|[,.;]|$)/u','medicamento'=>'/(?:para|por)\s+([^,.;]+?)(?=\s+(?:durante|a dosis|dosis|me lo recet[oó]|m[eé]dico)\b|[,.;]|$)/u','tiempo_uso'=>'/(?:durante|desde hace)\s+([^,.;]+?)(?=\s+(?:a dosis|dosis|me lo recet[oó]|m[eé]dico)\b|[,.;]|$)/u','dosis'=>'/dosis\s+([^,.;]+?)(?=\s+(?:me lo recet[oó]|m[eé]dico)\b|[,.;]|$)/u','medico'=>'/(?:m[eé]dico|recet[oó])\s+(?:por|de|:)?\s*([^,.;]+)$/u'];
        foreach($patterns as $field=>$pattern)if(preg_match($pattern,$n,$m))$fields[$field]=trim($m[1]);
        if(!$fields['descripcion']){ $parts=preg_split('/\s+para\s+/u',$text,2);if(count($parts)===2)$fields['descripcion']=trim(preg_replace('/^(tomo|tomando)\s+/iu','',$parts[0])); }
        $missing=array_keys(array_filter($fields,static fn($v):bool=>$v===null||trim((string)$v)===''));
        if($missing)return ['mode'=>'deterministic','kind'=>'clarification','target'=>'m06','missing'=>$missing,'clarification'=>'Para completar la medicación indica: nombre, motivo de uso, tiempo de tratamiento, dosis y médico que la recetó.','requires_confirmation'=>false,'can_confirm'=>false];
        return ['mode'=>'deterministic','kind'=>'m06_proposal','target'=>'m06','fields'=>$fields,'structured_value'=>$fields,'interpretation'=>'Propuesta de medicación actual preparada con los cinco campos del contrato.','requires_confirmation'=>true,'can_confirm'=>false];
    }

    public function capture(int $patientId,array $proposal,?string $expectedVersion): array
    {
        if($patientId<1||($proposal['kind']??'')!=='m06_proposal'||($proposal['target']??'')!=='m06'||!is_array($proposal['fields']??null))return ['resultado'=>422,'mensaje'=>'La propuesta M06 no es válida.'];
        $fields=[];foreach(['descripcion','medicamento','tiempo_uso','dosis','medico'] as $name){$value=trim((string)($proposal['fields'][$name]??''));if($value==='')return ['resultado'=>422,'mensaje'=>'Falta un campo obligatorio de M06.'];$fields[$name]=$value;}
        $db=$this->connection();$lockName='clinica_m06_capture_'.$patientId;$lock=$db->prepare('SELECT GET_LOCK(:name,5)');$lock->execute([':name'=>$lockName]);if((int)$lock->fetchColumn()!==1)return ['resultado'=>409,'mensaje'=>'No fue posible confirmar M06; inténtalo de nuevo.'];
        try{$state=$this->read($patientId);if(!$state['write_enabled'])return ['resultado'=>409,'mensaje'=>'M06 está finalizado o su estado editable no está disponible.'];if($expectedVersion!==null&&!hash_equals((string)$state['version'],$expectedVersion))return ['resultado'=>409,'mensaje'=>'M06 cambió mientras se revisaba. Recarga antes de confirmar.'];$db->beginTransaction();$model=new \App\Models\MedicacionActualModel();$created=$model->agregarMedicacionPaciente(['idPaciente'=>$patientId]);if((int)($created['resultado']??0)!==200)throw new \RuntimeException('M06 insert failed');$q=$db->prepare('SELECT id FROM pac_medicacion_actual_modulo_6 WHERE id_paciente=:id ORDER BY id DESC LIMIT 1');$q->execute([':id'=>$patientId]);$id=(int)$q->fetchColumn();$edited=$model->editarMedicamentoPaciente(['idPaciente'=>$patientId,'idMedicamento'=>$id,'fields'=>$fields]);if((int)($edited['resultado']??0)!==200)throw new \RuntimeException('M06 update failed');$db->commit();$after=$this->read($patientId);$record=null;foreach($after['records'] as $row)if((int)$row['id']===$id){$record=$row;break;}return ['resultado'=>200,'mensaje'=>'Medicación M06 guardada.','record'=>$record,'version'=>$after['version']];}catch(\Throwable){if($db->inTransaction())$db->rollBack();return ['resultado'=>422,'mensaje'=>'No fue posible guardar M06.'];}finally{$release=$db->prepare('SELECT RELEASE_LOCK(:name)');$release->execute([':name'=>$lockName]);}
    }
}
