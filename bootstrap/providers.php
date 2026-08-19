<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    // Registrar Laravel Fortify para autenticación
    FortifyServiceProvider::class,
];
