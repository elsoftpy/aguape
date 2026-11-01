<?php

namespace Database\Factories;

use App\Models\ConfiguracionGeneral;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConfiguracionGeneral>
 */
class ConfiguracionGeneralFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'intervalo_transmision_minutos' => ConfiguracionGeneral::INTERVALO_MINIMO,
            'version' => 1,
        ];
    }
}
