<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Solo en la Pi: la nube recibe los cortes ya calculados.
Schedule::command('ith:registrar-cortes')->hourlyAt(5)->withoutOverlapping()
    ->when(fn (): bool => config('app.rol') === 'local');

Schedule::command('sincronizacion:enviar')->everyMinute()->withoutOverlapping()
    ->when(fn (): bool => config('app.rol') === 'local' && filled(config('services.sincronizacion.url')));
