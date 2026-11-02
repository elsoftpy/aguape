<?php

namespace App\Actions;

use App\Models\SincronizacionPendiente;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AplicarSincronizacion
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $columnas = [];

    /**
     * Upsert por el mismo id de la Pi con el query builder: no corren observers ni auditoría.
     *
     * @param  array<int, array{tabla: string, id: int, operacion: string, datos?: array<string, mixed>, rol?: string|null}>  $registros
     */
    public function __invoke(array $registros): int
    {
        $orden = array_flip(array_keys(SincronizacionPendiente::TABLAS));
        usort($registros, fn (array $a, array $b) => $orden[$a['tabla']] <=> $orden[$b['tabla']]);

        DB::transaction(function () use ($registros): void {
            foreach ($registros as $registro) {
                if ($registro['operacion'] === SincronizacionPendiente::ELIMINAR) {
                    DB::table($registro['tabla'])->where('id', $registro['id'])->delete();

                    continue;
                }

                $datos = array_intersect_key($registro['datos'] ?? [], array_flip($this->columnas($registro['tabla'])));
                $datos['id'] = $registro['id'];

                DB::table($registro['tabla'])->upsert([$datos], ['id'], array_keys($datos));

                if ($registro['tabla'] === 'users') {
                    User::query()->findOrFail($registro['id'])->syncRoles(array_filter([$registro['rol'] ?? null]));
                }
            }
        });

        return count($registros);
    }

    /**
     * @return array<int, string>
     */
    private function columnas(string $tabla): array
    {
        return $this->columnas[$tabla] ??= Schema::getColumnListing($tabla);
    }
}
