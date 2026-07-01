<?php

namespace App\Enums;

enum RentPaymentStatus: string
{
    case Active = 'active';
    case Voided = 'voided';
}
