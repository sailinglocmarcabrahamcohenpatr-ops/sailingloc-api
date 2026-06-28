<?php

namespace App\Enum;

enum StatutCompteEnum: string
{
    case INACTIF  = 'inactif';
    case ACTIF    = 'actif';
    case SUSPENDU = 'suspendu';
}
