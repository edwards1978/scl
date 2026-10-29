<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

/**
 * Bootstrap de pruebas.
 *
 * Se ejecuta antes de PHPUnit. Crea el esquema de test con el prefijo
 * configurado en phpunit.xml (test_) si aún no existe. Así la suite usa
 * tablas propias y no toca las tablas reales de la base de datos.
 */

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

// Migra/actualiza el esquema de pruebas con prefijo test_. Es idempotente:
// si ya está al día, no hace nada; si faltan migraciones nuevas, las aplica.
Artisan::call('migrate', ['--force' => true]);

// El kernel de consola instala sus propios manejadores de error/excepción;
// se restauran para que PHPUnit no marque todas las pruebas como "risky".
restore_error_handler();
restore_exception_handler();
