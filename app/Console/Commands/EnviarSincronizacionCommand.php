<?php

namespace App\Console\Commands;

use App\Actions\EnviarSincronizacion;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sincronizacion:enviar')]
#[Description('Envía a la nube los registros pendientes de sincronizar')]
class EnviarSincronizacionCommand extends Command
{
    public function handle(EnviarSincronizacion $enviar): int
    {
        if (config('app.rol') !== 'local' || blank(config('services.sincronizacion.url'))) {
            $this->info('Sincronización desactivada (solo corre en el servidor local con SINCRONIZACION_URL).');

            return self::SUCCESS;
        }

        $resultado = $enviar();

        $this->info("Enviados: {$resultado['enviados']} | pendientes: {$resultado['pendientes']}");

        if ($resultado['error'] !== null) {
            $this->warn("Falló el envío, se reintenta en el próximo ciclo: {$resultado['error']}");
        }

        return self::SUCCESS;
    }
}
