<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$database = 'clinica_dolor_browser_20261012';
$base = 'http://127.0.0.1:8099';
require $root . '/vendor/autoload.php';
foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line=trim($line); if ($line==='' || str_starts_with($line,'#') || !str_contains($line,'=')) continue;
    [$key,$value]=explode('=',$line,2); $_ENV[trim($key)]=trim($value," \t\n\r\0\x0B\"'");
}
$_ENV['DB_DATABASE']=$database;
$pdo = new PDO("mysql:host={$_ENV['DB_HOST']};port={$_ENV['DB_PORT']};dbname={$database};charset=utf8mb4", $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$tableNames = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

$token = static fn(int $patient, int $clinic): string => Firebase\JWT\JWT::encode(['iat'=>time(),'iss'=>$_ENV['APP_NAME'],'exp'=>time()+900,'id_clinica'=>$clinic,'id_usuario'=>$patient,'nombre'=>'Paciente sintético','vista'=>'historia-clinica','rol'=>'Paciente'], $_ENV['APP_KEY'], 'HS256');
$csrf = static fn(int $patient, string $jwt): string => hash_hmac('sha256','m03-capture:'.$patient.':'.hash('sha256',$jwt),$_ENV['APP_KEY']);
$request = static function(string $path,string $jwt,?array $body,?string $csrfToken) use ($base): array {
    $headers="Cookie: CLINICAJWT={$jwt}\r\n"; if($body!==null)$headers.="Content-Type: application/json\r\n"; if($csrfToken!==null)$headers.="X-M03-CSRF: {$csrfToken}\r\n";
    $ctx=stream_context_create(['http'=>['method'=>$body===null?'GET':'POST','ignore_errors'=>true,'follow_location'=>0,'header'=>$headers,'content'=>$body===null?'':json_encode($body,JSON_UNESCAPED_UNICODE),'timeout'=>8]]);
    $raw=@file_get_contents($base.$path,false,$ctx); $status=0; foreach($http_response_header??[] as $h)if(preg_match('/^HTTP\/\S+\s+(\d+)/',$h,$m)){$status=(int)$m[1];break;}
    return [$status,json_decode((string)$raw,true)?:[]];
};
$rows = static function(string $table,int $patient) use ($pdo,$tableNames): array { if (!in_array($table,$tableNames,true)) return []; return $pdo->query("SELECT * FROM {$table} WHERE id_paciente={$patient} ORDER BY id")->fetchAll(); };
$m04Before=$rows('pc_antecedentes_quirurgicos',9302);
$m02Before=$rows('pc_antecedentes_familiares',9302);
$m03Before=$rows('pac_respuestas_paciente_modulo_3',9302);
$finalBefore=$pdo->query('SELECT id,id_modulo,id_paciente FROM pac_historia_clinica_finalizar WHERE id_modulo=4 AND id_paciente=9302 ORDER BY id')->fetchAll();
$hash=static fn(array $x): string=>hash('sha256',json_encode($x,JSON_UNESCAPED_UNICODE));
$jwt=$token(9302,9102); $csrfToken=$csrf(9302,$jwt);
$state=(new App\Services\ConversationalM04Adapter($pdo))->read(9302);
[$interpretStatus,$interpret]=$request('/historia-clinica/entrevista/interpretar',$jwt,['idPaciente'=>9302,'modulo'=>'M04','utterance'=>'Me operaron de la vesícula hace cinco años'],$csrfToken);
$proposal=$interpret['proposal']??[];
[$captureStatus]=$request('/historia-clinica/entrevista/capturar-m04',$jwt,['idPaciente'=>9302,'expectedVersion'=>$state['version'],'proposal'=>$proposal],$csrfToken);
$m04AfterCapture=$rows('pc_antecedentes_quirurgicos',9302);
$createdRows=count($m04AfterCapture)===count($m04Before)+1;
$stored=array_values(array_filter($m04AfterCapture,static fn(array $r):bool=>!in_array($r,$m04Before,true)));
$storedOne=count($stored)===1 && trim((string)($stored[0]['cirugia']??''))!=='';
$stateAfter=(new App\Services\ConversationalM04Adapter($pdo))->read(9302);
[$staleStatus]=$request('/historia-clinica/entrevista/capturar-m04',$jwt,['idPaciente'=>9302,'expectedVersion'=>$state['version'],'proposal'=>$proposal],$csrfToken);
[$csrfStatus]=$request('/historia-clinica/entrevista/capturar-m04',$jwt,['idPaciente'=>9302,'expectedVersion'=>$stateAfter['version'],'proposal'=>$proposal],null);
[$negativeStatus,$negative]=$request('/historia-clinica/entrevista/interpretar',$jwt,['idPaciente'=>9302,'modulo'=>'M04','utterance'=>'Nunca he tenido cirugías'],$csrfToken);
[$clarifyStatus,$clarify]=$request('/historia-clinica/entrevista/interpretar',$jwt,['idPaciente'=>9302,'modulo'=>'M04','utterance'=>'Sí'],$csrfToken);
$noWriteHash=$hash($m04AfterCapture)===$hash($rows('pc_antecedentes_quirurgicos',9302));
$pdo->prepare('INSERT INTO pac_historia_clinica_finalizar (id_modulo,id_paciente) VALUES (4,9302)')->execute();
$finalState=(new App\Services\ConversationalM04Adapter($pdo))->read(9302);
[$finalStatus]=$request('/historia-clinica/entrevista/capturar-m04',$jwt,['idPaciente'=>9302,'expectedVersion'=>$finalState['version'],'proposal'=>$proposal],$csrfToken);
$m04AfterFinal=$rows('pc_antecedentes_quirurgicos',9302);
$jwtOther=$token(9301,9101); $csrfOther=$csrf(9301,$jwtOther);
[$crossStatus]=$request('/historia-clinica/entrevista/interpretar',$jwt,['idPaciente'=>9301,'modulo'=>'M04','utterance'=>'Sí, una cirugía'],$csrfOther);

// Restore only the tested synthetic patient/module to its exact baseline.
$pdo->beginTransaction();
try {
    $pdo->prepare('DELETE FROM pc_antecedentes_quirurgicos WHERE id_paciente=9302')->execute();
    $insert=$pdo->prepare('INSERT INTO pc_antecedentes_quirurgicos (id,id_paciente,fecha,cirugia,observaciones) VALUES (:id,9302,:fecha,:cirugia,:observaciones)');
    foreach($m04Before as $r)$insert->execute([':id'=>$r['id'],':fecha'=>$r['fecha'],':cirugia'=>$r['cirugia'],':observaciones'=>$r['observaciones']]);
    $pdo->prepare('DELETE FROM pac_historia_clinica_finalizar WHERE id_modulo=4 AND id_paciente=9302')->execute();
    $insertFinal=$pdo->prepare('INSERT INTO pac_historia_clinica_finalizar (id,id_modulo,id_paciente) VALUES (:id,4,9302)');
    foreach($finalBefore as $r)$insertFinal->execute([':id'=>$r['id']]);
    $pdo->commit();
} catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
$restored=$hash($m04Before)===$hash($rows('pc_antecedentes_quirurgicos',9302)) && $hash($finalBefore)===$hash($pdo->query('SELECT id,id_modulo,id_paciente FROM pac_historia_clinica_finalizar WHERE id_modulo=4 AND id_paciente=9302 ORDER BY id')->fetchAll());
$m02Unchanged = !in_array('pac_antecedentes_familiares',$tableNames,true) || $hash($m02Before)===$hash($rows('pc_antecedentes_familiares',9302));
$m03Unchanged = $hash($m03Before)===$hash($rows('pac_respuestas_paciente_modulo_3',9302));
echo json_encode(['database'=>$database,'m02_table_present'=>in_array('pac_antecedentes_familiares',$tableNames,true),'interpret'=>$interpretStatus,'positive_capture'=>$captureStatus,'structured_proposal'=>($proposal['kind']??'')==='m04_proposal' && ($proposal['surgery']??'')!=='','stored_one'=>$storedOne,'created_rows_only'=>$createdRows,'stale'=>$staleStatus,'csrf_missing'=>$csrfStatus,'negative'=>[$negativeStatus,$negative['proposal']['kind']??null],'clarification'=>[$clarifyStatus,$clarify['proposal']['kind']??null],'no_extra_write_after_reviews'=>$noWriteHash,'finalized'=>$finalStatus,'cross_clinic'=>$crossStatus,'m02_unchanged'=>$m02Unchanged,'m03_unchanged'=>$m03Unchanged,'m01_m02_m03_unchanged'=>$m02Unchanged && $m03Unchanged,'restored'=>$restored],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
