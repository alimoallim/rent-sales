<?php

namespace App\Support;

final class MoneyConfig
{
    public static function rentalCurrency(): string
    {
        return (string) config('money.rental.currency', 'KES');
    }

    public static function salesCurrency(): string
    {
        return (string) config('money.sales.currency', 'USD');
    }

    public static function currencyForModule(string $module): string
    {
        return $module === 'sales'
            ? self::salesCurrency()
            : self::rentalCurrency();
    }
}
