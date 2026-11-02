<?php

namespace App\Models;

use App\Models\Concerns\SeSincroniza;
use Carbon\CarbonInterface;
use Database\Factories\NodoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable([
    'establecimiento_id',
    'potrero_id',
    'codigo',
    'nombre',
    'activo',
    'instalado_en',
])]
class Nodo extends Model implements AuditableContract
{
    /** @use HasFactory<NodoFactory> */
    use Auditable, HasFactory, SeSincroniza;

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'instalado_en' => 'datetime',
        ];
    }

    public function estaTransmitiendo(?CarbonInterface $ultimaLectura): bool
    {
        return $this->activo
            && $ultimaLectura !== null
            && $ultimaLectura->gte(now()->subMinutes(ConfiguracionGeneral::actual()->intervalo_transmision_minutos * 2));
    }

    public function ithCortes(): HasMany
    {
        return $this->hasMany(IthCorte::class);
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function potrero(): BelongsTo
    {
        return $this->belongsTo(Potrero::class);
    }

    public function lecturas(): HasMany
    {
        return $this->hasMany(Lectura::class);
    }

    public function umbrales(): HasMany
    {
        return $this->hasMany(Umbral::class);
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(Alerta::class);
    }
}
