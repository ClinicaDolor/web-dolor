<?php
require_once __DIR__.'/../vendor/autoload.php';
require __DIR__ . '/../app/Config/constantes.php';
//require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';

use App\Core\Router;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$router = new Router();
$router->dispatch();
