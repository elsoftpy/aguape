<?php

namespace Database\Factories;

use App\Models\Lectura;
use App\Models\Nodo;
use App\Models\Variable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lectura>
 */
class LecturaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nodo_id' => Nodo::factory(),
            'variable_id' => Variable::factory(),
            'valor' => fake()->randomFloat(4, 0, 100),
            'medido_en' => now(),
        ];
    }
}
