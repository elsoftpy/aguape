<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

#[Fillable(['clave', 'nombre', 'unidad'])]
class Variable extends Model implements AuditableContract
{
    /** @use HasFactory<\Database\Factories\VariableFactory> */
    use Auditable, HasFactory;

    public function lecturas(): HasMany
    {
        return $this->hasMany(Lectura::class);
    }

    public function umbrales(): HasMany
    {
        return $this->hasMany(Umbral::class);
    }
}
