<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$database = 'clinica_dolor_browser_20261012';
$baseUrl = 'http://127.0.0.1:8098';
require $root . '/vendor/autoload.php';
$env = [];
foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) { $line=trim($line); if ($line===''||str_starts_with($line,'#')||!str_contains($line,'=')) continue; [$k,$v]=explode('=',$line,2); $env[trim($k)]=trim($v," \t\n\r\0\x0B\"'"); }
$pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',$env['DB_HOST'],$env['DB_PORT'],$database),$env['DB_USERNAME'],$env['DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) throw new RuntimeException('base aislada no activa');
$state = static function(PDO $pdo): array { $rows=$pdo->query('SELECT id,id_pregunta,id_paciente,respuesta FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301 ORDER BY id_pregunta,id')->fetchAll(); return array_map(static fn(array $r): array=>['id'=>(int)$r['id'],'q'=>(int)$r['id_pregunta'],'p'=>(int)$r['id_paciente'],'len'=>strlen((string)$r['respuesta']),'sha256'=>hash('sha256',(string)$r['respuesta'])],$rows); };
$before=$state($pdo);
$headers="Content-Type: application/json\r\n";
$context=stream_context_create(['http'=>['method'=>'POST','ignore_errors'=>true,'follow_location'=>0,'header'=>$headers,'content'=>json_encode(['pin'=>'PIN-Synth-2026']),'timeout'=>8]]);
$loginBody=@file_get_contents($baseUrl.'/historia-clinica/login',false,$context); $loginStatus=0; foreach($http_response_header??[] as $h) if(preg_match('/^HTTP\/\S+\s+(\d+)/',$h,$m)){$loginStatus=(int)$m[1];break;}
$login=is_array($d=json_decode((string)$loginBody,true))?$d:[]; $jwt=(string)($login['token']??'');
$readContext=stream_context_create(['http'=>['method'=>'GET','ignore_errors'=>true,'follow_location'=>0,'header'=>"Cookie: CLINICAJWT={$jwt}\r\n",'timeout'=>8]]);
$page=@file_get_contents($baseUrl.'/historia-clinica/entrevista',false,$readContext); $pageStatus=0; foreach($http_response_header??[] as $h) if(preg_match('/^HTTP\/\S+\s+(\d+)/',$h,$m)){$pageStatus=(int)$m[1];break;}
$after=$state($pdo);
echo json_encode(['database'=>$database,'login_http'=>$loginStatus,'login_token_received'=>($jwt!==''),'chat_get_http'=>$pageStatus,'chat_render_detected'=>str_contains((string)$page,'patient-interview-question'),'fixture_unchanged'=>($before===$after),'rows'=>count($after),'duplicates'=>(int)$pdo->query('SELECT COUNT(*) FROM (SELECT id_pregunta FROM pac_respuestas_paciente_modulo_3 WHERE id_paciente=9301 GROUP BY id_pregunta HAVING COUNT(*)>1) d')->fetchColumn()],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
