<?php

namespace App\Services\Rental;

use App\Models\TenantElectricityBill;
use App\Models\TenantWaterBill;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class MeterReadingResolver
{
    /**
     * @param  class-string<TenantWaterBill|TenantElectricityBill>  $billModel
     * @return array{
     *     previous_reading: int,
     *     previous_reading_locked: bool,
     *     is_first_reading: bool,
     *     default_rate: string,
     *     default_fixed_fee: string,
     *     prior_bill_id?: int
     * }
     */
    public function context(string $billModel, int $tenantId, int $month, int $year): array
    {
        $priorBill = $this->priorBill($billModel, $tenantId, $month, $year);

        if ($priorBill === null) {
            return [
                'previous_reading' => 0,
                'previous_reading_locked' => false,
                'is_first_reading' => true,
                'default_rate' => '50.00',
                'default_fixed_fee' => '0.00',
            ];
        }

        return [
            'previous_reading' => (int) $priorBill->current_reading,
            'previous_reading_locked' => true,
            'is_first_reading' => false,
            'default_rate' => (string) $priorBill->rate,
            'default_fixed_fee' => (string) $priorBill->fixed_fee,
            'prior_bill_id' => $priorBill->id,
        ];
    }

    /**
     * @param  class-string<TenantWaterBill|TenantElectricityBill>  $billModel
     */
    public function resolvePreviousReading(
        string $billModel,
        int $tenantId,
        int $month,
        int $year,
        ?int $submittedPrevious,
        int $currentReading,
    ): int {
        $context = $this->context($billModel, $tenantId, $month, $year);

        if ($context['previous_reading_locked']) {
            if ($submittedPrevious !== null && $submittedPrevious !== $context['previous_reading']) {
                throw ValidationException::withMessages([
                    'previous_reading' => ['Previous reading is fixed from the last recorded meter reading and cannot be changed.'],
                ]);
            }

            if ($currentReading < $context['previous_reading']) {
                throw ValidationException::withMessages([
                    'current_reading' => ["Current reading must be at least {$context['previous_reading']}."],
                ]);
            }

            return $context['previous_reading'];
        }

        $openingReading = $submittedPrevious ?? 0;

        if ($currentReading < $openingReading) {
            throw ValidationException::withMessages([
                'current_reading' => ["Current reading must be at least {$openingReading}."],
            ]);
        }

        return $openingReading;
    }

    /**
     * @param  class-string<TenantWaterBill|TenantElectricityBill>  $billModel
     */
    private function priorBill(string $billModel, int $tenantId, int $month, int $year): ?Model
    {
        return $billModel::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($month, $year): void {
                $query->where('billing_year', '<', $year)
                    ->orWhere(function ($query) use ($month, $year): void {
                        $query->where('billing_year', $year)
                            ->where('billing_month', '<', $month);
                    });
            })
            ->orderByDesc('billing_year')
            ->orderByDesc('billing_month')
            ->first();
    }
}
