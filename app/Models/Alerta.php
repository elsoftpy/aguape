<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\EstadoAlerta;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lectura_id',
    'umbral_id',
    'nodo_id',
    'variable_id',
    'estado',
    'atendida_por',
    'atendida_en',
    'notificada_en',
])]
class Alerta extends Model
{
    /** @use HasFactory<\Database\Factories\AlertaFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'estado' => EstadoAlerta::class,
            'atendida_en' => 'datetime',
            'notificada_en' => 'datetime',
        ];
    }

    public function lectura(): BelongsTo
    {
        return $this->belongsTo(Lectura::class);
    }

    public function umbral(): BelongsTo
    {
        return $this->belongsTo(Umbral::class);
    }
    
    public function nodo(): BelongsTo
    {
        return $this->belongsTo(Nodo::class);
    }

    public function variable(): BelongsTo
    {
        return $this->belongsTo(Variable::class);
    }

    public function atendidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendida_por');
    }
}
