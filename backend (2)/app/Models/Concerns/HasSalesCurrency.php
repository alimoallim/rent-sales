<?php

namespace App\Models\Concerns;

use App\Support\MoneyConfig;

trait HasSalesCurrency
{
    protected static function bootHasSalesCurrency(): void
    {
        static::creating(function (self $model): void {
            if (! isset($model->currency_code) || $model->currency_code === '') {
                $model->currency_code = MoneyConfig::salesCurrency();
            }
        });
    }
}
