<?php

use App\Http\Controllers\Api\ConfiguracionController;
use App\Http\Controllers\Api\IngestaController;
use Illuminate\Support\Facades\Route;

Route::post('/ingesta', [IngestaController::class, 'store'])
    ->middleware(['ingesta.token', 'ingesta.signature'])
    ->name('ingesta.store');

Route::get('/configuracion', [ConfiguracionController::class, 'show'])
    ->middleware(['ingesta.token', 'ingesta.signature'])
    ->name('configuracion.show');
