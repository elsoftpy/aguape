<?php

namespace App\Console\Commands;

use App\Models\SincronizacionPendiente;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('sincronizacion:encolar-todo')]
#[Description('Marca todos los registros locales como pendientes, para la primera sincronización con la nube')]
class EncolarSincronizacionCommand extends Command
{
    public function handle(): int
    {
        if (config('app.rol') !== 'local') {
            $this->error('Solo se puede encolar en el servidor local.');

            return self::FAILURE;
        }

        foreach (array_keys(SincronizacionPendiente::TABLAS) as $tabla) {
            $cantidad = 0;

            DB::table($tabla)->orderBy('id')->chunk(1000, function ($filas) use ($tabla, &$cantidad): void {
                foreach ($filas as $fila) {
                    SincronizacionPendiente::registrar($tabla, $fila->id);
                }

                $cantidad += $filas->count();
            });

            $this->line("{$tabla}: {$cantidad}");
        }

        return self::SUCCESS;
    }
}
