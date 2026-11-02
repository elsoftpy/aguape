<?php

namespace App\Models;

use App\Models\Concerns\SeSincroniza;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

#[Fillable(['nombre', 'ubicacion', 'hectareas'])]
class Establecimiento extends Model implements AuditableContract
{
    use Auditable, HasFactory, SeSincroniza;

    /**
     * Solo devuelve el establecimiento si hay uno solo registrado.
     */
    public static function unicoRegistrado(): ?self
    {
        $establecimientos = self::query()->limit(2)->get();

        return $establecimientos->count() === 1 ? $establecimientos->first() : null;
    }

    public function potreros(): HasMany
    {
        return $this->hasMany(Potrero::class);
    }

    public function nodos(): HasMany
    {
        return $this->hasMany(Nodo::class);
    }
}
