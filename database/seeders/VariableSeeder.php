<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Variable;
use Illuminate\Database\Seeder;

class VariableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $variables = [
            ['clave' => 'nivel_agua', 'nombre' => 'Nivel de agua', 'unidad' => 'cm'],
            ['clave' => 'ph_agua', 'nombre' => 'pH del agua', 'unidad' => 'pH'],
            ['clave' => 'temperatura_agua', 'nombre' => 'Temperatura del agua', 'unidad' => '°C'],
            ['clave' => 'turbidez_agua', 'nombre' => 'Turbidez del agua', 'unidad' => 'NTU'],
            ['clave' => 'conductividad_agua', 'nombre' => 'Conductividad del agua', 'unidad' => 'µS/cm'],
            ['clave' => 'temperatura_aire', 'nombre' => 'Temperatura del aire', 'unidad' => '°C'],
            ['clave' => 'humedad_aire', 'nombre' => 'Humedad relativa del aire', 'unidad' => '%'],
            ['clave' => 'humedad_suelo', 'nombre' => 'Humedad del suelo', 'unidad' => '%'],
            ['clave' => 'altura_pastura', 'nombre' => 'Altura de la pastura', 'unidad' => 'cm'],
            ['clave' => 'radiacion_solar', 'nombre' => 'Radiación solar', 'unidad' => 'W/m²'],
            ['clave' => 'precipitacion', 'nombre' => 'Precipitación pluvial', 'unidad' => 'mm'],
            ['clave' => 'nivel_bateria', 'nombre' => 'Nivel de batería del nodo', 'unidad' => '%'],
            ['clave' => 'ith', 'nombre' => 'Índice de Temperatura y Humedad (ITH)', 'unidad' => null],
        ];

        foreach ($variables as $variable) {
            Variable::query()->updateOrCreate(['clave' => $variable['clave']], $variable);
        }
    }
}
