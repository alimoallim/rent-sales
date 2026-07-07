<?php

namespace App\Enums;

enum ElectricityBillStatus: string
{
    case Recorded = 'recorded';
    case Paid = 'paid';
}
