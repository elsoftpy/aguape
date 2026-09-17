<?php

namespace Database\Factories;

use App\Models\Alerta;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Lectura;
use App\Models\Umbral;
use App\Enums\EstadoAlerta;

/**
 * @extends Factory<Alerta>
 */
class AlertaFactory extends Factory
{
    public function definition(): array
    {
        $lectura = Lectura::factory()->create();
        $umbral = Umbral::factory()->create(['variable_id' => $lectura->variable_id]);

        return [
            'lectura_id' => $lectura->id,
            'umbral_id' => $umbral->id,
            'nodo_id' => $lectura->nodo_id,
            'variable_id' => $lectura->variable_id,
            'estado' => EstadoAlerta::Pendiente,
        ];
    }
}
