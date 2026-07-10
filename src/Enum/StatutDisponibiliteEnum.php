<?php

namespace App\Enum;

enum StatutDisponibiliteEnum: string
{
    case DISPONIBLE   = 'disponible';
    case INDISPONIBLE = 'indisponible';
    case BLOQUE       = 'bloque';
}
