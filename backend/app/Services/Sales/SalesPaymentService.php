<?php

namespace App\Services\Sales;

use App\Enums\SalesPaymentStatus;
use App\Models\Client;
use App\Models\SalesPayment;
use App\Models\User;
use App\Services\Payments\PaymentIdempotencyGuard;
use App\Services\Payments\ReceiptNumberService;
use Illuminate\Support\Facades\DB;

class SalesPaymentService
{
    public function __construct(
        private readonly PaymentIdempotencyGuard $idempotencyGuard,
        private readonly ReceiptNumberService $receiptNumbers,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data, User $actor): SalesPayment
    {
        return DB::transaction(function () use ($data, $actor): SalesPayment {
            Client::query()->whereKey($data['client_id'])->lockForUpdate()->firstOrFail();

            $discount = $data['discount'] ?? 0;
            $userProvidedReference = filled($data['invoice_reference'] ?? null);
            $invoiceReference = $userProvidedReference ? $data['invoice_reference'] : null;

            $existing = $this->idempotencyGuard->findRecentDuplicate(
                SalesPayment::query()
                    ->where('client_id', $data['client_id'])
                    ->where('sale_building_id', $data['sale_building_id'])
                    ->where('status', SalesPaymentStatus::Active),
                (string) $data['amount'],
                (string) $discount,
                (string) $data['paid_at'],
                $actor->id,
                $invoiceReference,
                $userProvidedReference,
            );

            if ($existing instanceof SalesPayment) {
                return $existing->load(['client.unit', 'building']);
            }

            if (! $userProvidedReference) {
                $invoiceReference = $this->receiptNumbers->nextSales((int) $data['sale_building_id']);
            }

            $payment = SalesPayment::createActive([
                'client_id' => $data['client_id'],
                'sale_building_id' => $data['sale_building_id'],
                'amount' => $this->idempotencyGuard->normalizeMoney($data['amount']),
                'discount' => $this->idempotencyGuard->normalizeMoney($discount),
                'invoice_reference' => $invoiceReference,
                'bank' => $data['bank'] ?? null,
                'remark' => $data['remark'] ?? null,
                'paid_at' => $data['paid_at'],
            ], $actor->id);

            return $payment->load(['client.unit', 'building']);
        });
    }
}
