<?php

namespace App\Actions;

use App\Exceptions\NodoInactivoException;
use App\Models\Nodo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ProcesarIngestaNodo
{
    /**
     * Procesa, valida y almacena un mensaje de ingesta ya decodificado por
     * el servicio receptor LoRa (RF04): guarda una `Lectura` por variable
     * recibida (más `nivel_bateria`, RF02) y calcula el ITH (RF07) cuando el
     * mensaje trae temperatura y humedad del aire.
     *
     * @param  array{codigo: string, medido_en: string, nivel_bateria?: float|null, lecturas: array<int, array{variable: string, valor: float}>}  $datos
     */
    public function __invoke(array $datos): void
    {
        $nodo = Nodo::query()->where('codigo', $datos['codigo'])->firstOrFail();

        if (! $nodo->activo) {
            throw new NodoInactivoException($nodo->codigo);
        }

        // El medido_en del receptor trae su propio desfase: se pasa a la zona de la aplicación.
        $medidoEn = CarbonImmutable::parse($datos['medido_en'])->setTimezone(config('app.timezone'));
        $valoresPorClave = collect($datos['lecturas'])->pluck('valor', 'variable');

        DB::transaction(function () use ($nodo, $datos, $medidoEn, $valoresPorClave): void {
            if (array_key_exists('nivel_bateria', $datos) && $datos['nivel_bateria'] !== null) {
                $this->guardarLectura($nodo, 'nivel_bateria', $medidoEn, (float) $datos['nivel_bateria']);
            }

            foreach ($datos['lecturas'] as $lectura) {
                $this->guardarLectura(
                    nodo: $nodo, 
                    claveVariable: $lectura['variable'], 
                    medidoEn: $medidoEn, 
                    valor: (float) $lectura['valor']
                );
            }

            if ($valoresPorClave->has('temperatura_aire') && $valoresPorClave->has('humedad_aire')) {
                app(CalcularIth::class)(
                    $nodo,
                    $medidoEn,
                    (float) $valoresPorClave->get('temperatura_aire'),
                    (float) $valoresPorClave->get('humedad_aire'),
                );
            }
        });
    }

    private function guardarLectura(Nodo $nodo, string $claveVariable, CarbonImmutable $medidoEn, float $valor): void
    {
        app(GuardarLectura::class)(
            nodo: $nodo, 
            claveVariable: $claveVariable, 
            medidoEn: $medidoEn,
            valor: $valor
        );
    }
}