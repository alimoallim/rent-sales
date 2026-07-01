<?php

namespace App\Enums;

enum ElectricityBillStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
}
