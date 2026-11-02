<?php

use App\Http\Controllers\Api\ConfiguracionController;
use App\Http\Controllers\Api\IngestaController;
use App\Http\Controllers\Api\SincronizacionController;
use Illuminate\Support\Facades\Route;

Route::post('/ingesta', [IngestaController::class, 'store'])
    ->middleware(['rol:local', 'ingesta.token', 'ingesta.signature'])
    ->name('ingesta.store');

Route::get('/configuracion', [ConfiguracionController::class, 'show'])
    ->middleware(['rol:local', 'ingesta.token', 'ingesta.signature'])
    ->name('configuracion.show');

Route::post('/sincronizacion', [SincronizacionController::class, 'store'])
    ->middleware(['rol:nube', 'sincronizacion.firma'])
    ->name('sincronizacion.store');
