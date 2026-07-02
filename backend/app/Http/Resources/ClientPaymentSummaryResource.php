<?php

namespace App\Http\Resources;

use App\Support\MoneyConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientPaymentSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'agreed_sale_price' => $this->resource['agreed_sale_price'],
            'currency_code' => $this->resource['currency_code'] ?? MoneyConfig::salesCurrency(),
            'deposit' => $this->resource['deposit'],
            'payments_total' => $this->resource['payments_total'],
            'discounts_total' => $this->resource['discounts_total'],
            'paid_total' => $this->resource['paid_total'],
            'balance' => $this->resource['balance'],
            'status' => $this->resource['status'],
        ];
    }
}
