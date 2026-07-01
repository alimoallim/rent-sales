<?php

namespace App\Enums;

enum WaterBillStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
}
