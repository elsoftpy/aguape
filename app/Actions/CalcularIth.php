<?php

namespace App\Actions;

use App\Models\Lectura;
use App\Models\Nodo;
use Carbon\CarbonInterface;

class CalcularIth
{
    /**
     * Calcula el ITH a partir de una lectura de temperatura y humedad del
     * aire del mismo nodo y momento, y lo registra como una lectura de la
     * variable `ith` (RF07).
     */
    public function __invoke(Nodo $nodo, CarbonInterface $medidoEn, float $temperaturaAmbiente, float $humedadRelativa): Lectura
    {
        return app(GuardarLectura::class)(
            nodo: $nodo,
            claveVariable: 'ith',
            medidoEn: $medidoEn,
            valor: self::formula(temperaturaAmbiente: $temperaturaAmbiente, humedadRelativa: $humedadRelativa),
        ); 
    }

    /**
     * Fórmula: THI = (1,8 x Ta + 32) - (0,55 - 0,55 x HR/100) x (1,8 x Ta - 26).
     * Ejemplo (Ta=31°C, HR=56,8% => ITH=80,7).
     */
    public static function formula(float $temperaturaAmbiente, float $humedadRelativa): float
    {
        return round(
            (1.8 * $temperaturaAmbiente + 32) - ((0.55 - 0.55 * $humedadRelativa / 100) * (1.8 * $temperaturaAmbiente - 26)),
            2
        );
    }
}