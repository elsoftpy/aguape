<?php

namespace App\Actions;

use App\Enums\TipoUmbral;
use App\Models\Alerta;
use App\Models\Lectura;
use App\Models\Umbral;

class GenerarAlertasLectura
{
    /**
     * Compara una lectura contra los umbrales activos de su variable (RF09)
     * y genera una `Alerta` por cada uno que se incumpla.
     *
     * Un umbral por nodo (`umbrales.nodo_id`) tiene prioridad sobre uno
     * global (`nodo_id` nulo) para la misma variable+tipo; se resuelve por
     * separado para `minimo` y `maximo`, así que una lectura puede violar
     * ambos a la vez (dos alertas) si están configurados.
     */

    /**
     * Invoke the class instance.
     */
    public function __invoke(Lectura $lectura): void
    {
        foreach (TipoUmbral::cases() as $tipo) {
            $umbral = $this->resolverUmbral(lectura: $lectura, tipo: $tipo);

            if ($umbral === null || ! $this->incumple(
                lectura: $lectura,
                umbral: $umbral,
                tipo: $tipo
            )) {
                continue;
            }

            Alerta::query()->create([
                'lectura_id' => $lectura->id,
                'umbral_id' => $umbral->id,
                'nodo_id' => $lectura->nodo_id,
                'variable_id' => $lectura->variable_id,
            ]);
        }

    }

    private function resolverUmbral(Lectura $lectura, TipoUmbral $tipo): ?Umbral
    {
        $umbralPorNodo = Umbral::query()
            ->where('variable_id', $lectura->variable_id)
            ->where('nodo_id', $lectura->nodo_id)
            ->where('tipo', $tipo)
            ->where('activo', true)
            ->first();

        return $umbralPorNodo ?? Umbral::query()
            ->where('variable_id', $lectura->variable_id)
            ->whereNull('nodo_id')
            ->where('tipo', $tipo)
            ->where('activo', true)
            ->first();
    }

    private function incumple(Lectura $lectura, Umbral $umbral, TipoUmbral $tipo): bool
    {
        return match ($tipo) {
            TipoUmbral::Minimo => $lectura->valor < $umbral->valor,
            TipoUmbral::Maximo => $lectura->valor > $umbral->valor,
        };
    }
}
