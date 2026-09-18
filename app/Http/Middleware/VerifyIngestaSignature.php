<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyIngestaSignature
{
    /**
     * Verifica la firma HMAC del cuerpo del mensaje de ingesta (RF04),
     * usando un secreto distinto al bearer token de `VerifyIngestaToken`.
     *
     * Defensa en profundidad: el bearer token puede filtrarse a través de
     * logs de acceso/proxy sin que el cuerpo de la request quede expuesto
     * junto a él; con un secreto separado, ese token filtrado no alcanza
     * para falsificar un mensaje. También acota el margen de reintento de
     * un mensaje capturado (replay), rechazando firmas con timestamp fuera
     * de tolerancia.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.ingesta.hmac_secret');
        $timestamp = $request->header('X-Ingesta-Timestamp');
        $firma = (string) $request->header('X-Ingesta-Signature');

        if ($secret === '' || $timestamp === null || ! ctype_digit((string) $timestamp)) {
            $this->rechazar($request, 'firma_invalida');
        }

        if (abs(time() - (int) $timestamp) > (int) config('services.ingesta.signature_tolerance_seconds', 300)) {
            $this->rechazar($request, 'timestamp_expirado');
        }

        $firmaEsperada = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        if (! hash_equals($firmaEsperada, $firma)) {
            $this->rechazar($request, 'firma_invalida');
        }

        return $next($request);
    }

    private function rechazar(Request $request, string $motivo): never
    {
        Log::channel('ingesta')->warning($motivo === 'timestamp_expirado' ? 'rechazado_timestamp_expirado' : 'rechazado_firma_invalida', [
            'codigo' => $request->input('codigo'),
        ]);

        abort(401, 'Firma de ingesta inválida.');
    }
}