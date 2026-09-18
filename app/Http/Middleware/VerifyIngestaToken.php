<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyIngestaToken
{
     /**
     * Verifica el token estático que autentica al servicio receptor LoRa
     * (RF04), en vez de un usuario autenticado.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('services.ingesta.token');

        if ($token === '' || ! hash_equals($token, (string) $request->bearerToken())) {
            Log::channel('ingesta')->warning('rechazado_token_invalido', [
                'codigo' => $request->input('codigo'),
            ]);

            abort(401, 'Token de ingesta inválido.');
        }

        return $next($request);
    }
}
