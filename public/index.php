<?php

use App\Http\Request;
use Illuminate\Foundation\Application;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Soporte para publicar la aplicacion en un subdirectorio (http://host/scl).
// Con Alias + mod_rewrite, Apache expone SCRIPT_NAME como "/index.php" y
// Laravel interpretaria la ruta como "/scl/login". Restauramos el nombre
// real del script para que el path info sea "/login".
$uri = $_SERVER['REQUEST_URI'] ?? '';
$isSubdir = str_starts_with($uri, '/scl');

if ($isSubdir) {
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = '/scl';

    $_SERVER['SCRIPT_NAME'] = $basePath.'/index.php';
    $_SERVER['PHP_SELF'] = $basePath.'/index.php';

    putenv("APP_URL={$scheme}://{$host}{$basePath}");
}

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
