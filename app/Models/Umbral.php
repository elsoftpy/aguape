<?php

namespace App\Models;

use App\Models\Concerns\SeSincroniza;
use App\Enums\TipoUmbral;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable(['variable_id', 'nodo_id', 'tipo', 'valor', 'activo'])]
class Umbral extends Model implements AuditableContract
{
    /** @use HasFactory<\Database\Factories\UmbralFactory> */
    use Auditable, HasFactory, SeSincroniza;

    protected $table = 'umbrales';

    protected function casts(): array 
    {
        return [
            'tipo' => TipoUmbral::class,
            'valor' => 'decimal:4',
            'activo' => 'boolean',
        ];
    }

    public function variable(): BelongsTo
    {
        return $this->belongsTo(Variable::class);
    }

    public function nodo(): BelongsTo
    {
        return $this->belongsTo(Nodo::class);
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(Alerta::class);
    }
}
