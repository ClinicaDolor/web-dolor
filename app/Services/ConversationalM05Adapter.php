<?php

namespace App\Services;

use App\Config\Database;
use PDO;

/** Conversational M05 adapter over the existing V1 pathological-history contract. */
final class ConversationalM05Adapter
{
    public function __construct(private ?PDO $db = null) {}
    private function connection(): PDO { return $this->db ?: Database::getInstance(); }

    /**
     * M05 V1 uses the catalog structure as its dependency contract: the first
     * select question in a theme is the parent and following input questions
     * in that same theme are affirmative-only details. This projection is
     * deliberately independent of visible wording and does not add schema
     * columns or infer a negative from an unknown response.
     */
    public static function resolveV1Eligibility(array $questions): array
    {
        $parentByTheme = [];
        foreach ($questions as $index => $row) {
            if (!is_array($row)) continue;
            $themeId = (int)($row['theme_id'] ?? 0);
            if ($themeId < 1 || (string)($row['tipo'] ?? '') !== 'select') continue;
            if (!array_key_exists($themeId, $parentByTheme)) $parentByTheme[$themeId] = $index;
        }

        $resolved = [];
        foreach ($questions as $index => $row) {
            if (!is_array($row)) continue;
            $themeId = (int)($row['theme_id'] ?? 0);
            $value = trim((string)($row['respuesta'] ?? ''));
            $type = (string)($row['tipo'] ?? '');
            $parentIndex = $parentByTheme[$themeId] ?? null;
            $isParent = $parentIndex !== null && $parentIndex === $index;
            $eligible = true;
            $dependencyState = 'not_applicable';
            $historicalPreserved = false;
            $needsClarification = false;
            $parentQuestionId = null;

            if ($isParent && $type === 'select') {
                $dependencyState = 'parent';
                if ($value !== '' && !in_array($value, ['Si', 'Sí', 'No'], true)) {
                    $needsClarification = true;
                    $dependencyState = 'unknown_parent';
                }
            } elseif ($parentIndex !== null && $type !== 'select') {
                $parent = $questions[$parentIndex] ?? [];
                $parentQuestionId = (int)($parent['question_id'] ?? 0);
                $parentValue = trim((string)($parent['respuesta'] ?? ''));
                if ($parentValue === 'Si' || $parentValue === 'Sí') {
                    $dependencyState = 'enabled_by_affirmative';
                } elseif ($parentValue === 'No') {
                    $eligible = false;
                    $dependencyState = $value === ''
                        ? 'skipped_by_negative'
                        : 'skipped_by_negative_historical_preserved';
                    $historicalPreserved = $value !== '';
                } elseif ($parentValue === '') {
                    $eligible = false;
                    $dependencyState = 'pending_parent';
                } else {
                    $eligible = false;
                    $dependencyState = 'blocked_by_unknown_parent';
                }
            }

            $resolvedRow = $row;
            $resolvedRow['eligible'] = $eligible;
            $resolvedRow['dependency_parent_question_id'] = $parentQuestionId;
            $resolvedRow['dependency_state'] = $dependencyState;
            $resolvedRow['historical_preserved'] = $historicalPreserved;
            $resolvedRow['needs_clarification'] = $needsClarification;
            $resolved[] = $resolvedRow;
        }

        return $resolved;
    }

    /**
     * Binary M05 answers are classified from the complete utterance. Known,
     * contextual or not-remembered responses never become a silent No.
     */
    public static function classifyBinaryResponse(string $utterance): array
    {
        $text = trim($utterance);
        $normalized = mb_strtolower($text, 'UTF-8');
        $unknownOrContextual = [
            '/\bno\s+(lo|la)\s+sé\b/u',
            '/\bno\s+recuerdo\b/u',
            '/\bdesconozco\b/u',
            '/\bno\s+estoy\s+segur[oa]\b/u',
            '/\bahora\s+no\b.*\bpero\b.*\b(s[ií]|antes)\b/u',
            '/\bantes\s+(s[ií])\b/u',
        ];
        foreach ($unknownOrContextual as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return ['value' => null, 'classification' => 'ambiguous_or_unknown', 'clarification' => 'Aclara si la respuesta a esta pregunta es sí o no.'];
            }
        }

        $yes = preg_match('/\b(s[ií]|afirmativo|correcto|diagnosticad[oa])\b/u', $normalized) === 1;
        $no = preg_match('/\b(no|nunca|negativo)\b/u', $normalized) === 1;
        if ($yes && !$no) return ['value' => 'Si', 'classification' => 'explicit_yes'];
        if ($no && !$yes) return ['value' => 'No', 'classification' => 'explicit_no'];
        return ['value' => null, 'classification' => 'ambiguous_or_unknown', 'clarification' => 'Responde sí o no para esta pregunta M05.'];
    }

    public function read(int $patientId): array
    {
        if ($patientId < 1) throw new \InvalidArgumentException('PATIENT_INVALID');
        $db = $this->connection();
        $q = $db->prepare('SELECT t.id AS theme_id,t.tema,q.id AS question_id,q.pregunta,q.tipo,COALESCE(r.id,0) AS response_id,COALESCE(r.respuesta,"") AS respuesta FROM pac_temas_modulo_5 t INNER JOIN pac_preguntas_modulo_5 q ON q.id_tema=t.id LEFT JOIN pac_respuestas_paciente_modulo_5 r ON r.id_pregunta=q.id AND r.id_paciente=:id ORDER BY t.id,q.id');
        $q->execute([':id'=>$patientId]); $questions=$q->fetchAll(PDO::FETCH_ASSOC);
        $d = $db->prepare('SELECT e.id AS disease_id,e.descripcion AS enfermedad,COALESCE(r.id,0) AS response_id,COALESCE(r.respuesta,"") AS respuesta,COALESCE(r.tipo,"") AS tipo,COALESCE(r.year_diagnostico,"") AS year_diagnostico FROM pac_enfermedades_modulo_5 e LEFT JOIN pac_enfermedades_respuestas_modulo_5 r ON r.id_enfermedad=e.id AND r.id_paciente=:id ORDER BY e.id');
        $d->execute([':id'=>$patientId]); $diseases=$d->fetchAll(PDO::FETCH_ASSOC);
        $questions = self::resolveV1Eligibility($questions);
        $policy=(new ClinicalWriteEligibilityPolicy($db))->evaluate($patientId,5); $pending=null;
        foreach($questions as $row) {
            $value = trim((string)($row['respuesta'] ?? ''));
            $needsClarification = ($row['needs_clarification'] ?? false) === true;
            if (($row['eligible'] ?? true) === true && ($value === '' || $needsClarification)) { $pending=$row; break; }
        }
        return ['key'=>'M05','title'=>'Antecedentes personales patológicos','questions'=>$questions,'diseases'=>$diseases,'pending_question'=>$pending,'version'=>hash('sha256',json_encode(['questions'=>$questions,'diseases'=>$diseases],JSON_UNESCAPED_UNICODE)),'finalized'=>(bool)($policy['finalized']??true),'write_enabled'=>(bool)($policy['editable']??false),'write_blocker'=>$policy['reason']??null,'policy_source'=>$policy['source']??'pac_historia_clinica_finalizar','disease_write_enabled'=>(bool)($policy['editable']??false),'disease_write_blocker'=>$policy['reason']??null];
    }

    public function interpret(int $questionId,string $utterance): array
    {
        if($questionId<1||trim($utterance)==='') throw new \InvalidArgumentException('Indica una respuesta M05.');
        $s=$this->connection()->prepare('SELECT id,id_tema,pregunta,tipo FROM pac_preguntas_modulo_5 WHERE id=:id LIMIT 1'); $s->execute([':id'=>$questionId]); $question=$s->fetch(PDO::FETCH_ASSOC);
        if(!$question) throw new \DomainException('La pregunta M05 no pertenece al catálogo autorizado.');
        $text=trim($utterance); $type=(string)$question['tipo'];
        if($type==='select') { $classification=self::classifyBinaryResponse($text); if(($classification['value']??null)===null)return ['mode'=>'deterministic','kind'=>'clarification','question_id'=>$questionId,'clarification'=>$classification['clarification']??'Responde sí o no para esta pregunta M05.','requires_confirmation'=>false,'can_confirm'=>false]; $value=(string)$classification['value']; }
        else $value=$text;
        return ['mode'=>'deterministic','kind'=>'m05_proposal','target'=>'v1','question_id'=>(int)$question['id'],'theme_id'=>(int)$question['id_tema'],'question_type'=>$type,'structured_value'=>$value,'detail'=>$value,'interpretation'=>'Respuesta M05 validada conforme al tipo del catálogo.','requires_confirmation'=>false,'can_confirm'=>false,'direct_save'=>true];
    }

    public function capture(int $patientId,array $proposal,?string $expectedVersion): array
    {
        if($patientId<1||($proposal['kind']??'')!=='m05_proposal'||($proposal['target']??'')!=='v1') return ['resultado'=>422,'mensaje'=>'La propuesta M05 no es válida.'];
        $questionId=(int)($proposal['question_id']??0); $themeId=(int)($proposal['theme_id']??0); $value=trim((string)($proposal['structured_value']??$proposal['detail']??''));
        if($questionId<1||$themeId<1||$value==='') return ['resultado'=>422,'mensaje'=>'La respuesta M05 no es válida.'];
        $db=$this->connection(); $lockName='clinica_m05_capture_'.$patientId.'_'.$questionId; $lock=$db->prepare('SELECT GET_LOCK(:name,5)'); $lock->execute([':name'=>$lockName]);
        if((int)$lock->fetchColumn()!==1) return ['resultado'=>409,'mensaje'=>'No fue posible confirmar M05; inténtalo de nuevo.'];
        try {
            $state=$this->read($patientId); if(!$state['write_enabled']) return ['resultado'=>409,'mensaje'=>'M05 está finalizado o su estado editable no está disponible.'];
            if($expectedVersion!==null&&!hash_equals((string)$state['version'],$expectedVersion)) return ['resultado'=>409,'mensaje'=>'M05 cambió mientras se revisaba. Recarga antes de confirmar.'];
            $question=null; foreach($state['questions'] as $row) if((int)$row['question_id']===$questionId&&(int)$row['theme_id']===$themeId){$question=$row;break;}
            if(!$question||(string)$question['tipo']!==(string)($proposal['question_type']??$question['tipo'])) return ['resultado'=>403,'mensaje'=>'La pregunta M05 no está autorizada.'];
            if (($question['eligible'] ?? true) !== true) return ['resultado'=>409,'mensaje'=>'La pregunta M05 no está disponible según la respuesta anterior.'];
            if($question['tipo']==='select'&&!in_array($value,['Si','No'],true)) return ['resultado'=>422,'mensaje'=>'La respuesta M05 no coincide con el catálogo.'];
            if($question['tipo']==='select'&&$value==='No') foreach($state['questions'] as $row) if((int)$row['theme_id']===$themeId&&(int)$row['question_id']!==$questionId&&trim((string)$row['respuesta'])!=='') return ['resultado'=>409,'mensaje'=>'M05 tiene respuestas dependientes; no se modificaron.'];
            $result=(new \App\Models\AntecedentesPatologicosModel())->editarCuestionarioV1Modulo(['idPaciente'=>$patientId,'idRespuesta'=>(int)$question['response_id'],'idPregunta'=>$questionId,'idTema'=>$themeId,'detalle'=>$value,'idTipo'=>(string)$question['tipo']]);
            if((int)($result['resultado']??0)!==200)return ['resultado'=>(int)($result['resultado']??422),'mensaje'=>'No fue posible guardar M05.'];
            $after=$this->read($patientId); $record=null; foreach($after['questions'] as $row) if((int)$row['question_id']===$questionId&&(int)$row['theme_id']===$themeId){$record=$row;break;}
            if (!is_array($record) || trim((string)($record['respuesta'] ?? '')) !== $value) {
                return ['resultado'=>500,'mensaje'=>'La respuesta M05 se guardó, pero el servidor no confirmó el readback.','readback'=>false];
            }
            $next=$after['pending_question']??null;
            $nextQuestion=is_array($next)?['question_id'=>(int)$next['question_id'],'theme_id'=>(int)$next['theme_id'],'tema'=>(string)$next['tema'],'pregunta'=>(string)$next['pregunta'],'tipo'=>(string)$next['tipo'],'stable_id'=>'M05:v1:'.(int)$next['question_id']]:null;
            $nextDisease=null;
            foreach (($after['diseases'] ?? []) as $disease) {
                if (trim((string)($disease['respuesta'] ?? '')) === '') {
                    $nextDisease=['disease_id'=>(int)$disease['disease_id'],'enfermedad'=>(string)$disease['enfermedad'],'stable_id'=>'M05:v2:'.(int)$disease['disease_id']];
                    break;
                }
            }
            return ['resultado'=>200,'mensaje'=>'Respuesta M05 guardada.','record'=>$record,'version'=>$after['version'],'readback'=>true,'next_question'=>$nextQuestion,'next_disease'=>$nextDisease];
        } catch(\Throwable){return ['resultado'=>422,'mensaje'=>'No fue posible guardar M05.'];}
        finally{$release=$db->prepare('SELECT RELEASE_LOCK(:name)');$release->execute([':name'=>$lockName]);}
    }

    public function interpretV2(int $diseaseId, string $utterance): array
    {
        if ($diseaseId < 1 || trim($utterance) === '') throw new \InvalidArgumentException('Indica una respuesta M05 V2.');
        $db = $this->connection();
        $stmt = $db->prepare('SELECT id,descripcion FROM pac_enfermedades_modulo_5 WHERE id=:id LIMIT 1');
        $stmt->execute([':id'=>$diseaseId]); $disease=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$disease) throw new \DomainException('La enfermedad M05 no pertenece al catálogo autorizado.');
        $text=trim($utterance); $n=mb_strtolower($text,'UTF-8');
        $classification=self::classifyBinaryResponse($text);
        if (($classification['value'] ?? null) === null) {
            return ['mode'=>'deterministic','kind'=>'clarification','target'=>'v2','disease_id'=>$diseaseId,'disease'=>$disease['descripcion'],'clarification'=>'Indica si has sido diagnosticado con esta enfermedad: sí o no.','requires_confirmation'=>false,'can_confirm'=>false];
        }
        $response=(string)$classification['value']; $yes=$response==='Si'; $changes=[['edicion'=>1,'detalle'=>$response]];
        $dependent=in_array((string)$disease['descripcion'],['Diabetes Mellitus','Enfermedad pulmonar'],true);
        if ($yes && $dependent) {
            $type=null;
            foreach (['Tipo 1'=>'tipo 1','Tipo 2'=>'tipo 2','Gestacional'=>'gestacional','EPOC'=>'epoc','Asma'=>'asma'] as $value=>$needle) if (str_contains($n,$needle)) {$type=$value;break;}
            $year=null; if(preg_match('/\b(19|20)\d{2}\b/u',$n,$m))$year=$m[0];
            if ($type===null || $year===null) return ['mode'=>'deterministic','kind'=>'clarification','target'=>'v2','disease_id'=>$diseaseId,'disease'=>$disease['descripcion'],'response'=>'Si','missing'=>array_values(array_filter(['tipo'=>$type===null?'tipo de enfermedad':null,'year_diagnostico'=>$year===null?'año de diagnóstico':null])),'clarification'=>'Para completar esta respuesta afirmativa indica el tipo de enfermedad y el año aproximado del diagnóstico.','requires_confirmation'=>false,'can_confirm'=>false];
            $changes[]=['edicion'=>2,'detalle'=>$type]; $changes[]=['edicion'=>3,'detalle'=>$year];
        }
        return ['mode'=>'deterministic','kind'=>'m05_v2_proposal','target'=>'v2','disease_id'=>$diseaseId,'disease'=>$disease['descripcion'],'changes'=>$changes,'structured_value'=>['respuesta'=>$response,'tipo'=>$changes[1]['detalle']??'','year_diagnostico'=>$changes[2]['detalle']??''],'interpretation'=>'Respuesta M05 V2 validada conforme al catálogo y sus dependencias.','requires_confirmation'=>false,'can_confirm'=>false,'direct_save'=>true];
    }

    public function captureV2(int $patientId,array $proposal,?string $expectedVersion): array
    {
        if($patientId<1||($proposal['kind']??'')!=='m05_v2_proposal'||($proposal['target']??'')!=='v2')return ['resultado'=>422,'mensaje'=>'La propuesta M05 V2 no es válida.'];
        $diseaseId=(int)($proposal['disease_id']??0);$changes=$proposal['changes']??[];
        if($diseaseId<1||!is_array($changes)||!$changes)return ['resultado'=>422,'mensaje'=>'La propuesta M05 V2 no contiene cambios confirmables.'];
        $db=$this->connection();$lockName='clinica_m05_v2_capture_'.$patientId.'_'.$diseaseId;$lock=$db->prepare('SELECT GET_LOCK(:name,5)');$lock->execute([':name'=>$lockName]);if((int)$lock->fetchColumn()!==1)return ['resultado'=>409,'mensaje'=>'No fue posible confirmar M05 V2; inténtalo de nuevo.'];
        try{$state=$this->read($patientId);if(!$state['write_enabled'])return ['resultado'=>409,'mensaje'=>'M05 está finalizado o su estado editable no está disponible.'];if($expectedVersion!==null&&!hash_equals((string)$state['version'],$expectedVersion))return ['resultado'=>409,'mensaje'=>'M05 cambió mientras se revisaba. Recarga antes de confirmar.'];$disease=null;foreach($state['diseases'] as $row)if((int)$row['disease_id']===$diseaseId){$disease=$row;break;}if(!$disease)return ['resultado'=>403,'mensaje'=>'La enfermedad M05 V2 no está autorizada.'];
            $normalized=[];foreach($changes as $change){$edition=(int)($change['edicion']??0);$detail=trim((string)($change['detalle']??''));if(!in_array($edition,[1,2,3],true)||$detail==='')return ['resultado'=>422,'mensaje'=>'Los campos dependientes M05 V2 no son válidos.'];if($edition===1&&!in_array($detail,['Si','No'],true))return ['resultado'=>422,'mensaje'=>'La respuesta M05 V2 debe ser Sí o No.'];if($edition===2&&!in_array($detail,['Tipo 1','Tipo 2','Gestacional','EPOC','Asma'],true))return ['resultado'=>422,'mensaje'=>'El tipo M05 V2 no pertenece al catálogo.'];if($edition===3&&!preg_match('/^(19|20)\d{2}$/',$detail))return ['resultado'=>422,'mensaje'=>'El año M05 V2 no es válido.'];$normalized[$edition]=$detail;}
            if(($normalized[1]??'')==='Si'&&in_array((string)$disease['enfermedad'],['Diabetes Mellitus','Enfermedad pulmonar'],true)&&(!isset($normalized[2])||!isset($normalized[3])))return ['resultado'=>422,'mensaje'=>'Faltan campos dependientes M05 V2.'];
            $db->beginTransaction();$model=new \App\Models\AntecedentesPatologicosModel();foreach($normalized as $edition=>$detail){$result=$model->editarCuestionarioV2Modulo(['idPaciente'=>$patientId,'idEnfermedad'=>(int)$disease['response_id'],'idEnfermedadCatalogo'=>$diseaseId,'edicion'=>$edition,'detalle'=>$detail]);if((int)($result['resultado']??0)!==200)throw new \RuntimeException('M05 V2 persistence failed');}$db->commit();$after=$this->read($patientId);$record=null;foreach($after['diseases'] as $row)if((int)$row['disease_id']===$diseaseId){$record=$row;break;}if(!is_array($record))return ['resultado'=>500,'mensaje'=>'La respuesta M05 V2 se guardó, pero el servidor no confirmó el readback.','readback'=>false];$nextDisease=null;foreach($after['diseases'] as $row)if(trim((string)$row['respuesta'])===''){ $nextDisease=['disease_id'=>(int)$row['disease_id'],'enfermedad'=>(string)$row['enfermedad'],'stable_id'=>'M05:v2:'.(int)$row['disease_id']];break;}return ['resultado'=>200,'mensaje'=>'Respuesta M05 V2 guardada.','record'=>$record,'version'=>$after['version'],'readback'=>true,'next_disease'=>$nextDisease];
        }catch(\Throwable){if($db->inTransaction())$db->rollBack();return ['resultado'=>422,'mensaje'=>'No fue posible guardar M05 V2.'];}finally{$release=$db->prepare('SELECT RELEASE_LOCK(:name)');$release->execute([':name'=>$lockName]);}
    }
}
