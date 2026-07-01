<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin array<string, string> */
class TenantPaymentSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'water_owed' => $this->resource['water_owed'],
            'electricity_owed' => $this->resource['electricity_owed'],
            'services_owed' => $this->resource['services_owed'],
            'rent_owed' => $this->resource['rent_owed'],
            'total_due' => $this->resource['total_due'],
            'credit_balance' => $this->resource['credit_balance'],
            'status' => $this->resource['status'],
            'labels' => [
                'water_owed' => 'Water Owed',
                'electricity_owed' => 'Electricity Owed',
                'services_owed' => 'Services Owed',
                'rent_owed' => 'Rent Owed',
                'total_due' => 'Total Due',
                'credit_balance' => 'Credit Balance',
            ],
            'meter_reading_reminders' => $this->resource['meter_reading_reminders'] ?? [],
            'payment_blocked' => (bool) ($this->resource['payment_blocked'] ?? false),
            'contract' => $this->resource['contract'] ?? null,
        ];
    }
}
