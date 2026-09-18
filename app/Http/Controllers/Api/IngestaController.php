<?php

namespace App\Http\Controllers\Api;

use App\Actions\ProcesarIngestaNodo;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIngestaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class IngestaController extends Controller
{
    public function store(StoreIngestaRequest $request, ProcesarIngestaNodo $procesarIngesta): JsonResponse
    {
        $datos = $request->validated();

        $procesarIngesta(datos: $datos);

        Log::channel('ingesta')->info('aceptado', [
            'codigo' => $datos['codigo'],
            'variables' => count($datos['lecturas']),
        ]);

        return response()->json(data: ['message' => 'Ingesta procesada.'], status: 201);
    }
}
