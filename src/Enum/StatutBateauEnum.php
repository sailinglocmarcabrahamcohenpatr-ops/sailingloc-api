<?php

namespace App\Enum;

enum StatutBateauEnum: string
{
    case DISPONIBLE  = 'disponible';
    case LOUE        = 'loué';
    case MAINTENANCE = 'maintenance';
    case INDISPONIBLE = 'en attente de validation';
}
