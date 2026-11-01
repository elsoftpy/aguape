<?php

namespace Database\Factories;

use App\Models\Nodo;
use App\Models\Establecimiento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Nodo>
 */
class NodoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'establecimiento_id' => Establecimiento::factory(),
            'potrero_id' => null,
            'codigo' => 'N-'.fake()->unique()->numberBetween(1, 999),
            'nombre' => fake()->words(2, true),
            'activo' => true,
            'instalado_en' => now(),
        ];
    }
}
