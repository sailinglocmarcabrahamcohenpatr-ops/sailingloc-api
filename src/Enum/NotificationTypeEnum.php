<?php

namespace App\Enum;

enum NotificationTypeEnum: string
{
    case NOUVELLE_RESERVATION   = 'nouvelle_reservation';
    case RESERVATION_CONFIRMEE  = 'reservation_confirmee';
    case NOUVEL_AVIS            = 'nouvel_avis';
}
