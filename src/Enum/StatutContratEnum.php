<?php

namespace App\Enum;

enum StatutContratEnum: string
{
    case EN_ATTENTE    = 'en_attente';
    case SIGNE         = 'signe';
    case ANNULE        = 'annule';
    case EXPIRE        = 'expire';
}
