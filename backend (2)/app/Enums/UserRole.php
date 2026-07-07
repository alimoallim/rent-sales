<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Rental = 'rental';
    case Sales = 'sales';
}
