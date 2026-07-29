<?php

namespace App\Enum;

enum StatutPaiementEnum: string
{
    case EN_ATTENTE = 'en_attente';
    case PAYE       = 'payé';
    case ECHOUE     = 'échoué';
    case REMBOURSE  = 'remboursé';
}
