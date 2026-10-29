<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

/**
 * Bootstrap de pruebas.
 *
 * Se ejecuta antes de PHPUnit. Migra el esquema de la base de pruebas
 * (`scl_test`) si aún no existe. La suite nunca toca la base de producción.
 *
 * La configuración se fija aquí, antes de arrancar Laravel, porque PHPUnit
 * carga este archivo antes de aplicar el bloque <php> de phpunit.xml. Si no se
 * hace, se termina usando bootstrap/cache/config.php de producción, con
 * APP_URL=http://localhost/scl, y las rutas de prueba devuelven 404.
 */
$basePath = dirname(__DIR__);
$testCachePath = $basePath.'/storage/framework/testing';

if (! is_dir($testCachePath)) {
    mkdir($testCachePath, 0775, true);
}

$environment = [
    'APP_ENV' => 'testing',
    'APP_URL' => 'http://localhost',
    'APP_MAINTENANCE_DRIVER' => 'file',
    'REGISTRATION_ENABLED' => 'true',
    'BCRYPT_ROUNDS' => '4',
    'CACHE_STORE' => 'array',
    'MAIL_MAILER' => 'array',
    'PULSE_ENABLED' => 'false',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
    'TELESCOPE_ENABLED' => 'false',
    'DB_CONNECTION' => getenv('DB_CONNECTION') ?: 'mysql',
    'DB_DATABASE' => 'scl_test',
    'DB_PREFIX' => '',
    'APP_CONFIG_CACHE' => $testCachePath.'/config.php',
    'APP_ROUTES_CACHE' => $testCachePath.'/routes-v7.php',
    'APP_EVENTS_CACHE' => $testCachePath.'/events.php',
    'APP_SERVICES_CACHE' => $testCachePath.'/services.php',
    'APP_PACKAGES_CACHE' => $testCachePath.'/packages.php',
];

foreach ($environment as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

// Una caché de test generada con una URL/base anterior volvería a romper la
// suite. Se descarta al inicio para que cada ejecución sea hermética.
foreach ([
    $environment['APP_CONFIG_CACHE'],
    $environment['APP_ROUTES_CACHE'],
    $environment['APP_EVENTS_CACHE'],
    $environment['APP_SERVICES_CACHE'],
    $environment['APP_PACKAGES_CACHE'],
] as $cacheFile) {
    if (is_file($cacheFile)) {
        unlink($cacheFile);
    }
}

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = (string) config('database.default');
$database = (string) config("database.connections.{$connection}.database");
$prefix = (string) config("database.connections.{$connection}.prefix");

// Protección: nunca migrar las tablas reales por accidente. Se permite un
// prefijo test_* o una base de datos distinta de la de producción.
if (app()->environment() !== 'testing') {
    fwrite(STDERR, "Las pruebas deben ejecutarse con APP_ENV=testing.\n");
    exit(1);
}
if ($database === 'scl' && $prefix === '') {
    fwrite(STDERR, "Las pruebas requieren DB_PREFIX=test_ o una base de datos de pruebas separada.\n");
    exit(1);
}
if ($prefix !== '' && ! str_starts_with($prefix, 'test_')) {
    fwrite(STDERR, "El prefijo de pruebas debe comenzar con test_.\n");
    exit(1);
}

// Migra/actualiza el esquema de pruebas. Es idempotente: si ya está al día,
// no hace nada; si faltan migraciones nuevas, las aplica.
Artisan::call('migrate', ['--force' => true]);

// El kernel de consola instala sus propios manejadores de error/excepción;
// se restauran para que PHPUnit no marque todas las pruebas como "risky".
restore_error_handler();
restore_exception_handler();
