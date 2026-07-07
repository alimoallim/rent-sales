<?php

namespace App\Enums;

enum ChargeBatchStatus: string
{
    case Draft = 'draft';
    case PartiallyApproved = 'partially_approved';
    case Locked = 'locked';
}
