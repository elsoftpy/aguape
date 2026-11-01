<?php

namespace App\Actions;

use App\Models\IthCorte;
use App\Models\Lectura;
use App\Models\Variable;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class RegistrarCortesIth
{
    /**
     * Recalcula los cortes ya terminados desde $desde (una lectura puede llegar tarde).
     */
    public function __invoke(CarbonInterface $desde): int
    {
        $variableIth = Variable::query()->where('clave', 'ith')->value('id');

        if ($variableIth === null) {
            return 0;
        }

        $registrados = 0;
        $inicio = IthCorte::inicioDelCorte($desde);

        while (($fin = $inicio->addHours(IthCorte::HORAS))->lte(now())) {
            $registrados += $this->registrarCorte($variableIth, $inicio, $fin);
            $inicio = $fin;
        }

        return $registrados;
    }

    private function registrarCorte(int $variableIth, CarbonImmutable $inicio, CarbonImmutable $fin): int
    {
        $resumenes = Lectura::query()
            ->where('variable_id', $variableIth)
            ->where('medido_en', '>=', $inicio)
            ->where('medido_en', '<', $fin)
            ->groupBy('nodo_id')
            ->selectRaw('nodo_id, avg(valor) as promedio, max(valor) as maximo, min(valor) as minimo, count(*) as cantidad')
            ->toBase()
            ->get();

        foreach ($resumenes as $resumen) {
            IthCorte::query()->updateOrCreate(
                ['nodo_id' => $resumen->nodo_id, 'inicio' => $inicio],
                [
                    'fin' => $fin,
                    'promedio' => round((float) $resumen->promedio, 2),
                    'maximo' => $resumen->maximo,
                    'minimo' => $resumen->minimo,
                    'cantidad_lecturas' => $resumen->cantidad,
                ],
            );
        }

        return $resumenes->count();
    }
}
