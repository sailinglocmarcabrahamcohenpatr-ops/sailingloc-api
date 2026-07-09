<?php

namespace App\Enum;

enum OwnerRequestStatusEnum: string
{
    case PENDING  = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
