<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Dynamically set APP_URL before bootstrap so route() generates
// correct URLs regardless of the domain/IP used to access the app.
$uri = $_SERVER['REQUEST_URI'] ?? '';
$isSubdir = str_starts_with($uri, '/scl');

if ($isSubdir) {
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = preg_replace('#/public(?=/index\.php)#', '', $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '/scl');
    $basePath = dirname($scriptName);
    putenv("APP_URL={$scheme}://{$host}{$basePath}");

    // Fix server vars for subdirectory routing
    foreach (['SCRIPT_NAME', 'PHP_SELF', 'ORIG_SCRIPT_NAME'] as $k) {
        if (isset($_SERVER[$k])) {
            $_SERVER[$k] = preg_replace('#/public(?=/index\.php)#', '', $_SERVER[$k]);
        }
    }
}

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
