<?php

namespace App\Enums;

enum PermissionsEnum: string
{
    case VerDashboard = 'ver-dashboard';
    case GestionarUmbrales = 'gestionar-umbrales';
    case GestionarNodos = 'gestionar-nodos';
    case GestionarPotreros = 'gestionar-potreros';
    case GestionarAlertas = 'gestionar-alertas';
    case VerHistorialAlertas = 'ver-historial-alertas';
    case GestionarUsuarios = 'gestionar-usuarios';
    case GestionarDestinatarios = 'gestionar-destinatarios';
    case VerAuditoria = 'ver-auditoria';
    case GestionarVariables = 'gestionar-variables';
    case GestionarFacturacion = 'gestionar-facturacion';
}
