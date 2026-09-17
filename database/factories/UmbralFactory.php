<?php

namespace Database\Factories;

use App\Enums\TipoUmbral;
use App\Models\Umbral;
use App\Models\Variable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Umbral>
 */
class UmbralFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'variable_id' => Variable::factory(),
            'nodo_id' => null,
            'tipo' => fake()->randomElement(TipoUmbral::cases()),
            'valor' => fake()->randomFloat(4, 0, 100),
            'activo' => true,
        ];
    }
}
