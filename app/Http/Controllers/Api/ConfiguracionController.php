<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracionGeneral;
use Illuminate\Http\JsonResponse;

class ConfiguracionController extends Controller
{
    public function show(): JsonResponse
    {
        $configuracion = ConfiguracionGeneral::actual();

        return response()->json([
            'intervalo_transmision_minutos' => $configuracion->intervalo_transmision_minutos,
            'cfg_version' => $configuracion->cfgVersionNodos(),
        ]);
    }
}
