<?php

namespace App\Enums;

enum PermissionsEnum: string
{
    case VerDashboard = 'ver-dashboard';
    case GestionarUmbrales = 'gestionar-umbrales';
    case GestionarAlertas = 'gestionar-alertas';
    case VerHistorialAlertas = 'ver-historial-alertas';
}
