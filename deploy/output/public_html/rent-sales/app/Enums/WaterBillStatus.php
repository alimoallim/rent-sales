<?php

namespace App\Enums;

enum WaterBillStatus: string
{
    case Recorded = 'recorded';
    case Paid = 'paid';
}
