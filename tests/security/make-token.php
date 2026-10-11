<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Firebase\JWT\JWT;

Dotenv::createImmutable(dirname(__DIR__, 2))->load();

if ($argc < 6) {
    fwrite(STDERR, "Uso: php make-token.php <vista> <id_clinica> <id_usuario> <nombre> <rol>\n");
    exit(2);
}

$claims = [
    'iat' => time(),
    'exp' => time() + 3600,
    'id_clinica' => (int) $argv[2],
    'id_usuario' => (int) $argv[3],
    'nombre' => $argv[4],
    'vista' => $argv[1],
    'rol' => $argv[5],
];

echo JWT::encode($claims, $_ENV['APP_KEY'], 'HS256');
