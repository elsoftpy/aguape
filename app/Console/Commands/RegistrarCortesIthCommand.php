<?php

namespace App\Console\Commands;

use App\Actions\RegistrarCortesIth;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ith:registrar-cortes {--desde= : Fecha (YYYY-MM-DD, hora local) desde la que recalcular; por defecto, las últimas 48 horas}')]
#[Description('Registra los cortes de seis horas del ITH por nodo')]
class RegistrarCortesIthCommand extends Command
{
    public function handle(RegistrarCortesIth $registrarCortes): int
    {
        $desde = $this->option('desde')
            ? CarbonImmutable::parse($this->option('desde'))
            : now()->subHours(48);

        $registrados = $registrarCortes($desde);

        $this->info("Cortes de ITH registrados: {$registrados}");

        return self::SUCCESS;
    }
}
