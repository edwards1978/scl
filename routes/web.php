<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\EmpleadoController;
use App\Http\Controllers\Web\ConfiguracionController;
use App\Http\Controllers\Web\EntornoController;
use App\Http\Controllers\Web\FeriadosController;
use App\Http\Controllers\Web\LiquidacionController;
use App\Http\Controllers\Web\MovimientosPrestacionesController;
use App\Http\Controllers\Web\PatronoController;
use App\Http\Controllers\Web\PrestacionesController;
use App\Http\Controllers\Web\ReportesController;
use App\Http\Controllers\Web\SalarioMinimoController;
use App\Http\Controllers\Web\SueldosController;
use App\Http\Controllers\Web\TasasBcvController;
use App\Http\Controllers\Web\TasasInteresController;
use App\Http\Controllers\Web\UtilidadesController;
use App\Http\Controllers\Web\VacacionesController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'contexto.patrono'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Entorno (contexto patrono)
    Route::prefix('entorno')->name('entorno.')->group(function () {
        Route::get('seleccionar', [EntornoController::class, 'seleccionar'])->name('seleccionar');
        Route::post('guardar', [EntornoController::class, 'guardar'])->name('guardar');
        Route::get('limpiar', [EntornoController::class, 'limpiar'])->name('limpiar');
    });

    // Configuración del patrono en contexto (utilidades, recibos, prestaciones)
    Route::prefix('configuracion')->name('configuracion.')->middleware('contexto.patrono')->group(function () {
        Route::get('/', [ConfiguracionController::class, 'index'])->name('index');
        Route::put('/', [ConfiguracionController::class, 'update'])->name('update');
    });

    // Días feriados (calendario laboral)
    Route::prefix('feriados')->name('feriados.')->group(function () {
        Route::get('/', [FeriadosController::class, 'index'])->name('index');
        Route::post('/', [FeriadosController::class, 'store'])->name('store');
        Route::put('{feriado}', [FeriadosController::class, 'update'])->name('update');
        Route::delete('{feriado}', [FeriadosController::class, 'destroy'])->name('destroy');
        Route::get('regenerar', [FeriadosController::class, 'regenerar'])->name('regenerar');
    });

    // Patronos
    Route::prefix('patronos')->name('patronos.')->group(function () {
        Route::get('/', [PatronoController::class, 'index'])->name('index');
        Route::get('create', [PatronoController::class, 'create'])->name('create');
        Route::post('/', [PatronoController::class, 'store'])->name('store');
        Route::get('{patrono}/edit', [PatronoController::class, 'edit'])->name('edit');
        Route::put('{patrono}', [PatronoController::class, 'update'])->name('update');
        Route::patch('{patrono}/toggle-estado', [PatronoController::class, 'toggleEstado'])->name('toggle');
        Route::delete('{patrono}', [PatronoController::class, 'destroy'])->name('destroy');
    });

    // Empleados (requieren contexto patrono)
    Route::prefix('empleados')->name('empleados.')->middleware('contexto.patrono')->group(function () {
        Route::get('/', [EmpleadoController::class, 'index'])->name('index');
        Route::get('create', [EmpleadoController::class, 'create'])->name('create');
        Route::post('/', [EmpleadoController::class, 'store'])->name('store');
        Route::get('{empleado}/edit', [EmpleadoController::class, 'edit'])->name('edit');
        Route::put('{empleado}', [EmpleadoController::class, 'update'])->name('update');
        Route::post('{empleado}/cambiar-patrono', [EmpleadoController::class, 'cambiarPatrono'])->name('cambiar-patrono');
        Route::delete('{empleado}', [EmpleadoController::class, 'destroy'])->name('destroy');
    });

    // Cálculo y control de vacaciones (LOTTT) — módulo global, bajo contexto patrono
    Route::prefix('vacaciones')->name('vacaciones.')->middleware('contexto.patrono')->group(function () {
        Route::get('/', [VacacionesController::class, 'index'])->name('index');
        Route::get('crear', [VacacionesController::class, 'create'])->name('create');
        Route::get('{vacacion}', [VacacionesController::class, 'show'])->name('show');
        Route::put('{vacacion}/estatus', [VacacionesController::class, 'estatus'])->name('estatus');
        Route::get('{vacacion}/pdf', [VacacionesController::class, 'reciboPdf'])->name('pdf');
        Route::delete('{vacacion}', [VacacionesController::class, 'destroy'])->name('destroy');
    });

    // Reportes (requieren contexto patrono)
    Route::prefix('reportes')->name('reportes.')->middleware('contexto.patrono')->group(function () {
        Route::get('vacaciones', [ReportesController::class, 'vacaciones'])->name('vacaciones.index');
        Route::get('vacaciones/pdf', [ReportesController::class, 'vacacionesPdf'])->name('vacaciones.pdf');
    });

    // Sueldos y variaciones salariales (requieren contexto patrono)
    Route::prefix('sueldos')->name('sueldos.')->middleware('contexto.patrono')->group(function () {
        Route::get('/', [SueldosController::class, 'index'])->name('index');
        Route::post('regenerar', [SueldosController::class, 'regenerar'])->name('regenerar');
        Route::get('variaciones', [SueldosController::class, 'variaciones'])->name('variaciones');
        Route::post('variaciones', [SueldosController::class, 'storeVariacion'])->name('variaciones.store');
        Route::delete('variaciones/{cambio}', [SueldosController::class, 'destroyVariacion'])->name('variaciones.destroy');
    });

    // Utilidades/Bonificación de fin de año (requieren contexto patrono)
    Route::prefix('utilidades')->name('utilidades.')->middleware('contexto.patrono')->group(function () {
        Route::get('/', [UtilidadesController::class, 'index'])->name('index');
        Route::post('calcular', [UtilidadesController::class, 'calcular'])->name('calcular');
        Route::post('{id}/recalcular', [UtilidadesController::class, 'recalcular'])->name('recalcular');
        Route::post('{id}/aprobar', [UtilidadesController::class, 'aprobar'])->name('aprobar');
        Route::get('{id}', [UtilidadesController::class, 'show'])->name('show');
        Route::get('{id}/pdf', [UtilidadesController::class, 'pdf'])->name('pdf');
        Route::delete('{id}', [UtilidadesController::class, 'destroy'])->name('destroy');
    });

    // Anticipos de prestaciones sociales y préstamos (Art. 144 LOTTT)
    Route::prefix('movimientos-prestaciones')->name('movimientos-prestaciones.')->middleware('contexto.patrono')->group(function () {
        Route::get('/', [MovimientosPrestacionesController::class, 'index'])->name('index');
        Route::get('crear', [MovimientosPrestacionesController::class, 'create'])->name('create');
        Route::post('/', [MovimientosPrestacionesController::class, 'store'])->name('store');
        Route::get('maximo/{empleadoId}', [MovimientosPrestacionesController::class, 'maximo'])->name('maximo');
        Route::get('{movimiento}/comprobante', [MovimientosPrestacionesController::class, 'comprobante'])->name('comprobante');
        Route::get('{movimiento}', [MovimientosPrestacionesController::class, 'show'])->name('show');
        Route::post('{movimiento}/intereses', [MovimientosPrestacionesController::class, 'registrarInteres'])->name('intereses');
        Route::post('{movimiento}/abonar', [MovimientosPrestacionesController::class, 'abonar'])->name('abonar');
        Route::post('{movimiento}/anular', [MovimientosPrestacionesController::class, 'anular'])->name('anular');
        Route::delete('{movimiento}', [MovimientosPrestacionesController::class, 'destroy'])->name('destroy');
    });

    // Prestaciones sociales: garantía e intereses (Arts. 142 y 143 LOTTT)
    Route::prefix('prestaciones')->name('prestaciones.')->middleware('contexto.patrono')->group(function () {
        Route::get('/', [PrestacionesController::class, 'index'])->name('index');
        Route::get('{empleadoId}', [PrestacionesController::class, 'show'])->name('show');
        Route::post('{empleadoId}/recalcular', [PrestacionesController::class, 'recalcular'])->name('recalcular');
        Route::post('{empleadoId}/intereses', [PrestacionesController::class, 'pagarInteres'])->name('intereses');
        Route::delete('{empleadoId}/intereses/{pago}', [PrestacionesController::class, 'destroyInteres'])->name('intereses.destroy');
        Route::get('{empleadoId}/pdf', [PrestacionesController::class, 'pdf'])->name('pdf');
        Route::get('{empleadoId}/hoja-calculo', [PrestacionesController::class, 'hojaCalculo'])->name('hoja-calculo');
        Route::post('{empleadoId}/proyectar', [PrestacionesController::class, 'proyectar'])->name('proyectar');
        Route::delete('{empleadoId}/proyeccion', [PrestacionesController::class, 'eliminarProyeccion'])->name('proyeccion.destroy');
    });

    // Liquidaciones de prestaciones sociales (Art. 142 lit. C LOTTT)
    Route::prefix('liquidaciones')->name('liquidaciones.')->middleware('contexto.patrono')->group(function () {
        Route::get('/', [LiquidacionController::class, 'index'])->name('index');
        Route::get('crear', [LiquidacionController::class, 'create'])->name('create');
        Route::get('{id}', [LiquidacionController::class, 'show'])->name('show');
        Route::post('{id}/aprobar', [LiquidacionController::class, 'aprobar'])->name('aprobar');
        Route::post('{id}/anular', [LiquidacionController::class, 'anular'])->name('anular');
        Route::put('{id}/conceptos', [LiquidacionController::class, 'actualizarConceptos'])->name('conceptos.update');
        Route::get('{id}/pdf', [LiquidacionController::class, 'pdf'])->name('pdf');
        Route::delete('{id}', [LiquidacionController::class, 'destroy'])->name('destroy');
    });

    // Tasas de cambio del BCV (no requieren patrono, son globales)
    Route::prefix('tasas-bcv')->name('tasas.')->group(function () {
        Route::get('/', [TasasBcvController::class, 'index'])->name('index');
        Route::post('/', [TasasBcvController::class, 'store'])->name('store');
        Route::post('importar', [TasasBcvController::class, 'importar'])->name('importar');
        Route::get('verificar', [TasasBcvController::class, 'verificar'])->name('verificar');
        Route::delete('{tasa}', [TasasBcvController::class, 'destroy'])->name('destroy');
    });

    // Tasas de interés sobre prestaciones sociales (BCV, globales)
    Route::prefix('tasas-interes')->name('tasas-interes.')->group(function () {
        Route::get('/', [TasasInteresController::class, 'index'])->name('index');
        Route::post('/', [TasasInteresController::class, 'store'])->name('store');
        Route::post('importar', [TasasInteresController::class, 'importar'])->name('importar');
        Route::delete('{tasa}', [TasasInteresController::class, 'destroy'])->name('destroy');
    });

    // Salarios mínimos legales (globales, de configuración, sin contexto patrono)
    Route::prefix('salarios-minimos')->name('salarios-minimos.')->group(function () {
        Route::get('/', [SalarioMinimoController::class, 'index'])->name('index');
        Route::post('/', [SalarioMinimoController::class, 'store'])->name('store');
        Route::put('{salario}', [SalarioMinimoController::class, 'update'])->name('update');
        Route::delete('{salario}', [SalarioMinimoController::class, 'destroy'])->name('destroy');
    });
});

require __DIR__.'/auth.php';
