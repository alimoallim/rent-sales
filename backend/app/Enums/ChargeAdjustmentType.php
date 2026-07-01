<?php

namespace App\Enums;

enum ChargeAdjustmentType: string
{
    case Rent = 'rent';
    case Service = 'service';
    case Water = 'water';
    case Electricity = 'electricity';
    case Credit = 'credit';
}
