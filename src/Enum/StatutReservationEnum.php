<?php

namespace App\Enum;

enum StatutReservationEnum: string
{
    case EN_ATTENTE = 'en_attente';
    case CONFIRMEE  = 'confirmée';
    case ANNULEE    = 'annulée';
    case REFUSEE    = 'refusée';
    case TERMINEE   = 'terminée';
}
