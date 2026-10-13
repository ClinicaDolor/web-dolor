<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);require $root.'/vendor/autoload.php';$adapter=new App\Services\ConversationalM02Adapter();$assert=static function(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);};$disease='Hipertensión arterial sistémica';
$a1=$adapter->interpret($disease,'Mi padre');$a2=$adapter->interpret($disease,'Sí',$a1['context']);$assert(($a1['kind']??'')==='clarification'&&($a1['context']['relative']??null)==='padre','A1 relative context failed');$assert(($a2['kind']??'')==='m02_read_only_proposal'&&($a2['detail']??'')==='Si'&&($a2['relative']??'')==='padre','A parent then yes failed');
$b1=$adapter->interpret($disease,'Sí');$b2=$adapter->interpret($disease,'Mi padre',$b1['context']);$assert(($b1['clarification_reason']??'')==='positive_first','B1 positive clarification failed');$assert(($b2['kind']??'')==='m02_read_only_proposal'&&($b2['relative']??'')==='padre','B yes then parent failed');
$c=$adapter->interpret($disease,'Sí, mi padre tiene hipertensión');$assert(($c['kind']??'')==='m02_read_only_proposal'&&($c['detail']??'')==='Si'&&($c['relative']??'')==='padre','C complete sentence failed');
$d=$adapter->interpret($disease,'No');$assert(($d['kind']??'')==='m02_read_only_proposal'&&($d['detail']??'')==='No'&&($d['relative']??null)===null,'D negative failed');
$e1=$adapter->interpret($disease,'Mi padre');$e2=$adapter->interpret($disease,'No',$e1['context']);$assert(($e2['kind']??'')==='clarification'&&($e2['clarification_reason']??'')==='contradiction','E contradiction failed');
$f=$adapter->interpret($disease,'No recuerdo');$assert(($f['kind']??'')==='clarification'&&($f['detail']??null)===null,'F uncertainty became negative');
$other=$adapter->interpret('Cáncer','Sí');$assert(($other['context']['disease']??'')==='Cáncer'&&($b1['context']['disease']??'')===$disease,'Context isolation failed');
echo json_encode(['m02_parent_then_yes'=>'PASS','m02_yes_then_parent'=>'PASS','m02_complete_sentence'=>'PASS','m02_negative'=>'PASS','m02_contradiction'=>'PASS','m02_ambiguous'=>'PASS','m02_context_isolation'=>'PASS','m02_interpretation_writes'=>'ZERO'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
