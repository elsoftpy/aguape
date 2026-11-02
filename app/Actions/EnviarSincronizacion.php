<?php

namespace App\Actions;

use App\Models\SincronizacionPendiente;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class EnviarSincronizacion
{
    /**
     * @return array{enviados: int, pendientes: int, error: string|null}
     */
    public function __invoke(int $maximoLotes = 20): array
    {
        $enviados = 0;
        $error = null;

        for ($lote = 0; $lote < $maximoLotes; $lote++) {
            $pendientes = SincronizacionPendiente::query()
                ->orderBy('id')
                ->limit((int) config('services.sincronizacion.lote'))
                ->get()
                ->sortBy(fn (SincronizacionPendiente $pendiente) => [$pendiente->prioridad(), $pendiente->id])
                ->values();

            if ($pendientes->isEmpty()) {
                break;
            }

            $error = $this->enviarLote($pendientes);

            if ($error !== null) {
                break;
            }

            $enviados += $pendientes->count();
        }

        $resultado = [
            'enviados' => $enviados,
            'pendientes' => SincronizacionPendiente::query()->count(),
            'error' => $error,
        ];

        if ($enviados > 0 || $error !== null) {
            Log::channel('sincronizacion')->info($error === null ? 'envio_ok' : 'envio_fallido', $resultado);
        }

        return $resultado;
    }

    /**
     * @param  Collection<int, SincronizacionPendiente>  $pendientes
     */
    private function enviarLote(Collection $pendientes): ?string
    {
        $cuerpo = json_encode(['registros' => $pendientes->map(fn (SincronizacionPendiente $pendiente) => $this->registro($pendiente))->all()]);
        $timestamp = (string) time();

        try {
            $respuesta = Http::withToken((string) config('services.sincronizacion.token'))
                ->withHeaders([
                    'X-Sincronizacion-Timestamp' => $timestamp,
                    'X-Sincronizacion-Signature' => hash_hmac('sha256', $timestamp.'.'.$cuerpo, (string) config('services.sincronizacion.hmac_secret')),
                ])
                ->acceptJson()
                ->timeout(30)
                ->withBody($cuerpo, 'application/json')
                ->post((string) config('services.sincronizacion.url'));
        } catch (Throwable $e) {
            return $this->marcarFallido($pendientes, $e->getMessage());
        }

        if (! $respuesta->successful()) {
            return $this->marcarFallido($pendientes, "HTTP {$respuesta->status()}: ".mb_substr($respuesta->body(), 0, 500));
        }

        foreach ($pendientes as $pendiente) {
            SincronizacionPendiente::query()
                ->whereKey($pendiente->id)
                ->where('version', $pendiente->version)
                ->delete();
        }

        return null;
    }

    /**
     * @return array{tabla: string, id: int, operacion: string, datos?: array<string, mixed>, rol?: string|null}
     */
    private function registro(SincronizacionPendiente $pendiente): array
    {
        $clase = SincronizacionPendiente::TABLAS[$pendiente->tabla];
        $modelo = $pendiente->operacion === SincronizacionPendiente::GUARDAR
            ? $clase::query()->find($pendiente->registro_id)
            : null;

        if ($modelo === null) {
            return ['tabla' => $pendiente->tabla, 'id' => $pendiente->registro_id, 'operacion' => SincronizacionPendiente::ELIMINAR];
        }

        $registro = [
            'tabla' => $pendiente->tabla,
            'id' => $pendiente->registro_id,
            'operacion' => SincronizacionPendiente::GUARDAR,
            'datos' => $modelo->getAttributes(),
        ];

        if ($modelo instanceof User) {
            unset($registro['datos']['remember_token']);
            $registro['rol'] = $modelo->roles()->value('name');
        }

        return $registro;
    }

    /**
     * @param  Collection<int, SincronizacionPendiente>  $pendientes
     */
    private function marcarFallido(Collection $pendientes, string $error): string
    {
        SincronizacionPendiente::query()
            ->whereKey($pendientes->modelKeys())
            ->update(['intentos' => DB::raw('intentos + 1'), 'ultimo_error' => $error]);

        return $error;
    }
}
