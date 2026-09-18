<?php

namespace App\Enums;

enum RolesEnum: string
{
    case Administrador = 'administrador';
    case Operador = 'operador';
    case Visor = 'visor';
}
