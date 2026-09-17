<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

#[Fillable([
    'establecimiento_id',
    'potrero_id',
    'codigo',
    'nombre',
    'intervalo_transmision_minutos',
    'activo',
    'instalado_en',
])]
class Nodo extends Model implements AuditableContract
{
    /** @use HasFactory<\Database\Factories\NodoFactory> */
    use Auditable, HasFactory;

    protected function casts(): array 
    {
        return [
            'activo' => 'boolean',
            'instalado_en' => 'datetime',
        ];
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
