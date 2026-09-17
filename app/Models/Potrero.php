<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

#[Fillable(['establecimiento_id', 'nombre', 'hectareas'])]
class Potrero extends Model implements AuditableContract
{
    /** @use HasFactory<\Database\Factories\PotreroFactory> */
    use Auditable, HasFactory;

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function nodos(): HasMany
    {
        return $this->hasMany(Nodo::class);
    }
}
