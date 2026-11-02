<?php

namespace App\Http\Controllers\Api;

use App\Actions\AplicarSincronizacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSincronizacionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SincronizacionController extends Controller
{
    public function store(StoreSincronizacionRequest $request, AplicarSincronizacion $aplicar): JsonResponse
    {
        $aplicados = $aplicar($request->validated('registros'));

        Log::channel('sincronizacion')->info('recibido', ['aplicados' => $aplicados]);

        return response()->json(['aplicados' => $aplicados]);
    }
}
