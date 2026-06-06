<?php

namespace App\Enum;

enum RoleEnum: string
{
    case USER         = 'ROLE_USER';
    case PROPRIETAIRE = 'ROLE_PROPRIETAIRE';
    case ADMIN        = 'ROLE_ADMIN';
}
