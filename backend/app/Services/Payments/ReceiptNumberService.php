<?php

namespace App\Services\Payments;

use App\Models\PaymentReceiptSequence;
use Illuminate\Support\Facades\DB;

class ReceiptNumberService
{
    public function nextRental(int $buildingId): string
    {
        return $this->next('rental', $buildingId);
    }

    public function nextSales(int $buildingId): string
    {
        return $this->next('sales', $buildingId);
    }

    private function next(string $module, int $buildingId): string
    {
        return DB::transaction(function () use ($module, $buildingId): string {
            $sequence = PaymentReceiptSequence::query()
                ->lockForUpdate()
                ->firstOrCreate(
                    [
                        'module' => $module,
                        'scope_id' => $buildingId,
                    ],
                    ['last_number' => 0],
                );

            $sequence->increment('last_number');

            $prefix = $module === 'rental'
                ? config('payments.rental_receipt_prefix', 'RCP')
                : config('payments.sales_receipt_prefix', 'SRCP');

            return sprintf('%s-%d-%06d', $prefix, $buildingId, $sequence->last_number);
        });
    }
}
