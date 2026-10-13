<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);$view=file_get_contents($root.'/app/views/paciente/entrevista.php');$assert=static function(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);};
$assert(str_contains($view,'data-m02-defer'),'M02 defer control missing');
$assert(str_contains($view,'Pregunta M02 pendiente. No se guardó'),'M02 defer must not write');
$assert(str_contains($view,'data-m02-quick'),'M02 quick responses missing');
$assert(str_contains($view,'processLocally'),'Local speech recognition gate missing');
$assert(str_contains($view,'recognitionConstructor.available'),'Voice language availability is not checked when exposed by the browser.');
$assert(str_contains($view,"state === 'available'"),'Voice availability must fail closed when local Spanish is unavailable.');
$assert(str_contains($view,'🎙 No disponible'),'Unavailable microphone state missing');
$assert(str_contains($view,'speechSynthesis'),'Local TTS missing');
$assert(str_contains($view,'stopAssistantVoice'),'TTS stop transition missing');
$assert(str_contains($view,'Comencemos con la siguiente pregunta pendiente'),'Obsolete smoking introduction remains');
echo "Voice/navigation contract: PASS (8 assertions; no database writes)\n";
