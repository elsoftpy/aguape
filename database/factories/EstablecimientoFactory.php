<?php

namespace Database\Factories;

use App\Models\Establecimiento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Establecimiento>
 */
class EstablecimientoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->company(),
            'ubicacion' => fake()->city(),
            'hectareas' => fake()->numberBetween(500, 5000),
        ];
    }
}
