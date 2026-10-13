<?php
declare(strict_types=1);
$root=dirname(__DIR__,2); require $root.'/vendor/autoload.php';
foreach(file($root.'/.env',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as $line){$line=trim($line);if($line===''||str_starts_with($line,'#')||!str_contains($line,'='))continue;[$k,$v]=explode('=',$line,2);$_ENV[trim($k)]=trim($v," \t\n\r\0\x0B\"'");}
$_ENV['DB_DATABASE']='clinica_dolor_browser_20261012';
$pdo=new PDO("mysql:host={$_ENV['DB_HOST']};port={$_ENV['DB_PORT']};dbname={$_ENV['DB_DATABASE']};charset=utf8mb4",$_ENV['DB_USERNAME'],$_ENV['DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$assert=static function(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);};
$adapter=new App\Services\ConversationalM05Adapter($pdo); $patient=9302; $before=$adapter->read($patient);
$assert(($before['key']??'')==='M05','M05 read contract missing'); $assert(array_key_exists('write_enabled',$before),'M05 write policy missing');
$pending=$before['pending_question']??null; $assert(is_array($pending),'Synthetic patient has no pending M05 V1 question');
$utterance=((string)$pending['tipo']==='select')?'Sí':'texto sintético de antecedentes';
$proposal=$adapter->interpret((int)$pending['question_id'],$utterance);
$assert(($proposal['kind']??'')==='m05_proposal'&&($proposal['target']??'')==='v1','M05 proposal invalid'); $assert(($proposal['can_confirm']??true)===false,'Interpretation must not authorize itself');
$clarify=((string)$pending['tipo']==='select')?$adapter->interpret((int)$pending['question_id'],'tal vez'):['kind'=>'not_applicable'];
$assert(((string)$pending['tipo']!=='select')||(($clarify['kind']??'')==='clarification'),'M05 clarification missing');
$cancel=$adapter->read($patient); $assert($cancel['version']===$before['version'],'Interpret/cancel changed M05');
$saved=$adapter->capture($patient,$proposal,$before['version']); $assert((int)($saved['resultado']??0)===200,'M05 synthetic capture failed');
$after=$adapter->read($patient); $found=null; foreach($after['questions'] as $row)if((int)$row['question_id']===(int)$pending['question_id']){$found=$row;break;}
$assert(is_array($found)&&trim((string)$found['respuesta'])===(string)$proposal['structured_value'],'M05 readback failed');
$stale=$adapter->capture($patient,$proposal,$before['version']); $assert((int)($stale['resultado']??0)===409,'M05 stale version did not return 409');
$rows=$pdo->prepare('SELECT id,id_pregunta FROM pac_respuestas_paciente_modulo_5 WHERE id_paciente=:id AND id_pregunta=:q');$rows->execute([':id'=>$patient,':q'=>(int)$pending['question_id']]);$assert(count($rows->fetchAll())===1,'M05 created duplicate response');
$pdo->prepare('DELETE FROM pac_respuestas_paciente_modulo_5 WHERE id_paciente=:id AND id_pregunta=:q')->execute([':id'=>$patient,':q'=>(int)$pending['question_id']]);
$restored=$adapter->read($patient); $assert($restored['version']===$before['version'],'M05 fixture was not restored');
$source=file_get_contents($root.'/app/Services/ConversationalM05Adapter.php');$assert(str_contains($source,'interpretV2')&&str_contains($source,'captureV2'),'V2 contract adapter missing');
echo "M05 conversational contract: PASS (read, proposal, clarification, capture, readback, stale 409, restore)\n";
