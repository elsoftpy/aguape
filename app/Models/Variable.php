<?php

namespace App\Models;

use Database\Factories\VariableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable(['clave', 'nombre', 'unidad'])]
class Variable extends Model implements AuditableContract
{
    /** @use HasFactory<VariableFactory> */
    use Auditable, HasFactory;

    /**
     * Ícono decorativo por variable (heroicon, no hay match 1:1 para todas).
     *
     * @var array<string, string>
     */
    private const ICONOS = [
        'nivel_agua' => 'beaker',
        'ph_agua' => 'eye-dropper',
        'temperatura_agua' => 'fire',
        'turbidez_agua' => 'cloud',
        'conductividad_agua' => 'bolt',
        'temperatura_aire' => 'sun',
        'humedad_aire' => 'cloud-arrow-down',
        'humedad_suelo' => 'square-3-stack-3d',
        'altura_pastura' => 'arrow-trending-up',
        'radiacion_solar' => 'presentation-chart-line',
        'precipitacion' => 'cloud-arrow-down',
        'ith' => 'variable',
    ];

    public function icono(): string
    {
        return self::ICONOS[$this->clave] ?? 'variable';
    }

    public function lecturas(): HasMany
    {
        return $this->hasMany(Lectura::class);
    }

    public function umbrales(): HasMany
    {
        return $this->hasMany(Umbral::class);
    }
}
