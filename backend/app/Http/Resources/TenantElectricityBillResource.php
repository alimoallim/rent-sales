<?php



namespace App\Http\Resources;



use App\Enums\ElectricityBillStatus;

use App\Support\MoneyConfig;

use Illuminate\Http\Request;

use Illuminate\Http\Resources\Json\JsonResource;



/** @mixin \App\Models\TenantElectricityBill */

class TenantElectricityBillResource extends JsonResource

{

    /**

     * @return array<string, mixed>

     */

    public function toArray(Request $request): array

    {

        $chargePosted = $this->relationLoaded('rentCharge')

            ? $this->rentCharge !== null

            : $this->rentCharge()->exists();



        return [

            'id' => $this->id,

            'currency_code' => MoneyConfig::rentalCurrency(),

            'tenant_id' => $this->tenant_id,

            'tenant_name' => $this->whenLoaded('tenant', fn () => $this->tenant->name),

            'rental_building_id' => $this->rental_building_id,

            'building_name' => $this->whenLoaded('building', fn () => $this->building->name),

            'billing_month' => $this->billing_month,

            'billing_year' => $this->billing_year,

            'previous_reading' => $this->previous_reading,

            'current_reading' => $this->current_reading,

            'consumption' => $this->consumption,

            'rate' => $this->rate,

            'fixed_fee' => $this->fixed_fee,

            'amount' => $this->amount,

            'amount_paid' => $this->amount_paid,

            'status' => $this->status->value,

            'status_label' => $this->statusLabel($chargePosted),

            'charge_posted' => $chargePosted,

            'rent_charge_id' => $this->whenLoaded('rentCharge', fn () => $this->rentCharge?->id),

            'remark' => $this->remark,

        ];

    }



    private function statusLabel(bool $chargePosted): string

    {

        if ($this->status === ElectricityBillStatus::Paid) {

            return 'Settled';

        }



        return $chargePosted ? 'Posted to balance' : 'Recorded';

    }

}

