<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

/**
 * Bootstrap de pruebas.
 *
 * Se ejecuta antes de PHPUnit. Migra el esquema de la base de pruebas
 * (`scl_test`) si aún no existe. La suite nunca toca la base de producción.
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

// Migra/actualiza el esquema de pruebas. Es idempotente: si ya está al día,
// no hace nada; si faltan migraciones nuevas, las aplica.
Artisan::call('migrate', ['--force' => true]);

// El kernel de consola instala sus propios manejadores de error/excepción;
// se restauran para que PHPUnit no marque todas las pruebas como "risky".
restore_error_handler();
restore_exception_handler();
