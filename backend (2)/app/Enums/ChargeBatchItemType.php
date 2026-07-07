<?php

namespace App\Enums;

enum ChargeBatchItemType: string
{
    case Rent = 'rent';
    case Service = 'service';
    case Water = 'water';
    case Electricity = 'electricity';
}
