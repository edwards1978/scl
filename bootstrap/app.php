<?php

use App\Http\Middleware\AutorizarGestionNomina;
use App\Http\Middleware\ValidarContextoPatrono;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'contexto.patrono' => ValidarContextoPatrono::class,
            'nomina.gestionar' => AutorizarGestionNomina::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson() || ! $request->is('login')) {
                return null;
            }

            return redirect()->route('login')->withErrors([
                'email' => 'La sesión expiró. Vuelve a intentarlo.',
            ]);
        });
    })->create();
