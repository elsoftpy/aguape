<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nodo_id', 'variable_id', 'valor', 'medido_en'])]
class Lectura extends Model
{
    /** @use HasFactory<\Database\Factories\LecturaFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:4',
            'medido_en' => 'datetime',
        ];
    }

    public function nodo(): BelongsTo
    {
        return $this->belongsTo(Nodo::class);
    }

    public function variable(): BelongsTo
    {
        return $this->belongsTo(Variable::class);
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(Alerta::class);
    }
}
