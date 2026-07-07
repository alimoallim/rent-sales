<?php

namespace App\Services\Rental;

use App\Enums\RentPaymentStatus;
use App\Models\RentPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payments\PaymentIdempotencyGuard;
use App\Services\Payments\ReceiptNumberService;
use Illuminate\Support\Facades\DB;

class RentPaymentService
{
    public function __construct(
        private readonly PaymentIdempotencyGuard $idempotencyGuard,
        private readonly ReceiptNumberService $receiptNumbers,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data, User $actor): RentPayment
    {
        return DB::transaction(function () use ($data, $actor): RentPayment {
            Tenant::query()->whereKey($data['tenant_id'])->lockForUpdate()->firstOrFail();

            $discount = $data['discount'] ?? 0;
            $userProvidedReference = filled($data['invoice_reference'] ?? null);
            $invoiceReference = $userProvidedReference ? $data['invoice_reference'] : null;

            $existing = $this->idempotencyGuard->findRecentDuplicate(
                RentPayment::query()
                    ->where('tenant_id', $data['tenant_id'])
                    ->where('rental_building_id', $data['rental_building_id'])
                    ->where('status', RentPaymentStatus::Active),
                (string) $data['amount'],
                (string) $discount,
                (string) $data['paid_at'],
                $actor->id,
                $invoiceReference,
                $userProvidedReference,
            );

            if ($existing instanceof RentPayment) {
                return $existing->load(['tenant', 'building']);
            }

            if (! $userProvidedReference) {
                $invoiceReference = $this->receiptNumbers->nextRental((int) $data['rental_building_id']);
            }

            $payment = RentPayment::createActive([
                'tenant_id' => $data['tenant_id'],
                'rental_building_id' => $data['rental_building_id'],
                'amount' => $this->idempotencyGuard->normalizeMoney($data['amount']),
                'discount' => $this->idempotencyGuard->normalizeMoney($discount),
                'invoice_reference' => $invoiceReference,
                'paid_at' => $data['paid_at'],
            ], $actor->id);

            return $payment->load(['tenant', 'building']);
        });
    }
}
