<?php

namespace App\Enums;

enum ChargeBatchItemStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Excluded = 'excluded';
}
