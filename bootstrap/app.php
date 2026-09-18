<?php

use App\Exceptions\NodoInactivoException;
use App\Http\Middleware\VerifyIngestaSignature;
use App\Http\Middleware\VerifyIngestaToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class, 
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'ingesta.token' => VerifyIngestaToken::class,
            'ingesta.signature' => VerifyIngestaSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (NodoInactivoException $e, Request $request){
            Log::channel('ingesta')->warning('rechazado_nodo_inactivo', [
                'codigo' => $e->codigoNodo,
            ]);

            return response()->json(['message' => $e->getMessage()], 409);
        });
    })->create();
