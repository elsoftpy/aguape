<?php

namespace Database\Factories;

use App\Models\IthCorte;
use App\Models\Nodo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IthCorte>
 */
class IthCorteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $inicio = IthCorte::inicioDelCorte(now()->subDay());
        $minimo = fake()->randomFloat(2, 60, 70);

        return [
            'nodo_id' => Nodo::factory(),
            'inicio' => $inicio,
            'fin' => $inicio->addHours(IthCorte::HORAS),
            'promedio' => $minimo + 5,
            'maximo' => $minimo + 10,
            'minimo' => $minimo,
            'cantidad_lecturas' => 24,
        ];
    }
}
