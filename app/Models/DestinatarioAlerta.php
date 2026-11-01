<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable(['email'])]
class DestinatarioAlerta extends Model implements AuditableContract
{
    /** @use HasFactory<\Database\Factories\DestinatarioAlertaFactory> */
    use Auditable, HasFactory;

    protected $table = 'destinatarios_alerta';
}
