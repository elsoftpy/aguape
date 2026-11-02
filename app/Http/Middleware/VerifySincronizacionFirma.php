<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifySincronizacionFirma
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('services.sincronizacion.token');
        $secreto = (string) config('services.sincronizacion.hmac_secret');
        $timestamp = $request->header('X-Sincronizacion-Timestamp');

        if ($token === '' || ! hash_equals($token, (string) $request->bearerToken())) {
            $this->rechazar('rechazado_token_invalido');
        }

        if ($secreto === '' || $timestamp === null || ! ctype_digit((string) $timestamp)) {
            $this->rechazar('rechazado_firma_invalida');
        }

        if (abs(time() - (int) $timestamp) > (int) config('services.sincronizacion.signature_tolerance_seconds', 300)) {
            $this->rechazar('rechazado_timestamp_expirado');
        }

        $firmaEsperada = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secreto);

        if (! hash_equals($firmaEsperada, (string) $request->header('X-Sincronizacion-Signature'))) {
            $this->rechazar('rechazado_firma_invalida');
        }

        return $next($request);
    }

    private function rechazar(string $motivo): never
    {
        Log::channel('sincronizacion')->warning($motivo);

        abort(401, 'Sincronización no autorizada.');
    }
}
