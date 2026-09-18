<?php

namespace App\Actions;

use App\Models\Lectura;
use App\Models\Nodo;
use App\Models\Variable;
use Carbon\CarbonInterface;

class GuardarLectura
{
    /**
     * Registra el valor de una variable para un nodo y momento dados de
     * forma idempotente (upsert por `nodo_id`+`variable_id`+`medido_en`),
     * compartido entre `ProcesarIngestaNodo` (RF01-RF04) y `CalcularIth`
     * (RF07) para no duplicar la clave de idempotencia en dos lugares.
     */
    public function __invoke(Nodo $nodo, string $claveVariable, CarbonInterface $medidoEn, float $valor): Lectura
    {
        $variable = Variable::query()->where('clave', $claveVariable)->firstOrFail();

        return Lectura::query()->updateOrCreate(
            [
                'nodo_id' => $nodo->id,
                'variable_id' => $variable->id,
                'medido_en' => $medidoEn,
            ],
            ['valor' => $valor],
        );
    }
}