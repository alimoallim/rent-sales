<?php

namespace App\Enums;

enum SalesPaymentStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
}
