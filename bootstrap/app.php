<?php

use App\Exceptions\NodoInactivoException;
use App\Http\Middleware\RequiereRol;
use App\Http\Middleware\VerifyIngestaSignature;
use App\Http\Middleware\VerifyIngestaToken;
use App\Http\Middleware\VerifyRecaptcha;
use App\Http\Middleware\VerifySincronizacionFirma;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'ingesta.token' => VerifyIngestaToken::class,
            'ingesta.signature' => VerifyIngestaSignature::class,
            'sincronizacion.firma' => VerifySincronizacionFirma::class,
            'rol' => RequiereRol::class,
        ]);

        $middleware->web(append: [
            VerifyRecaptcha::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (NodoInactivoException $e, Request $request) {
            Log::channel('ingesta')->warning('rechazado_nodo_inactivo', [
                'codigo' => $e->codigoNodo,
            ]);

            return response()->json(['message' => $e->getMessage()], 409);
        });
    })->create();
