<?php

namespace Database\Factories;

use App\Models\DestinatarioAlerta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DestinatarioAlerta>
 */
class DestinatarioAlertaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
