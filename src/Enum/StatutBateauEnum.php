<?php

namespace App\Enum;

enum StatutBateauEnum: string
{
    case EN_ATTENTE_VALIDATION = 'en attente de validation';
    case DISPONIBLE            = 'disponible';
    case LOUE                  = 'loué';
    case MAINTENANCE           = 'maintenance';
    case SUSPENDU              = 'suspendu';
}
