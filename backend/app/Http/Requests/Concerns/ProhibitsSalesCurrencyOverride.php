<?php

namespace App\Http\Requests\Concerns;

trait ProhibitsSalesCurrencyOverride
{
    /**
     * Sales amounts are always stored in the module currency (USD).
     * Clients must not send currency_code — it is set server-side.
     *
     * @return array<string, list<string>>
     */
    protected function prohibitSalesCurrencyOverride(): array
    {
        return [
            'currency_code' => ['prohibited'],
        ];
    }
}
