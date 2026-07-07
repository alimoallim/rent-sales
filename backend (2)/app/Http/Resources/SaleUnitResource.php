<?php

namespace App\Http\Resources;

use App\Services\Sales\ClientBalanceCalculator;
use App\Support\MoneyConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SaleUnit */
class SaleUnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_building_id' => $this->sale_building_id,
            'building_name' => $this->whenLoaded('building', fn () => $this->building->name),
            'house_number' => $this->house_number,
            'floor' => $this->floor,
            'description' => $this->description,
            'list_price' => $this->list_price,
            'currency_code' => $this->currency_code ?? MoneyConfig::salesCurrency(),
            'status' => $this->status->value,
            'sale_client' => $this->whenLoaded('saleClient', function () {
                if (! $this->saleClient) {
                    return null;
                }

                $summary = app(ClientBalanceCalculator::class)->summary($this->saleClient);

                return [
                    'id' => $this->saleClient->id,
                    'name' => $this->saleClient->name,
                    'agreed_sale_price' => $summary['agreed_sale_price'],
                    'paid_total' => $summary['paid_total'],
                    'balance' => $summary['balance'],
                    'payment_status' => $summary['status'],
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
