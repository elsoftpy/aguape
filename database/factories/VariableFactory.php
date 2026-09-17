<?php

namespace Database\Factories;

use App\Models\Variable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Variable>
 */
class VariableFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clave' => fake()->unique()->slug(2, false),
            'nombre' => fake()->words(3, true),
            'unidad' => fake()->randomElement(['°C', '%', 'cm', 'mm', 'l']),
        ];
    }
}
