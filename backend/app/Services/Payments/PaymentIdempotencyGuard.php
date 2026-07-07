<?php

namespace App\Services\Payments;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentIdempotencyGuard
{
    public function dedupWindowSeconds(): int
    {
        return (int) config('payments.dedup_window_seconds', 60);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return TModel|null
     */
    public function findRecentDuplicate(
        Builder $query,
        string $amount,
        string $discount,
        string $paidAt,
        int $createdBy,
        ?string $invoiceReference,
        bool $matchInvoiceReference = true,
    ): ?Model {
        $normalizedAmount = $this->normalizeMoney($amount);
        $normalizedDiscount = $this->normalizeMoney($discount);

        $query = $query
            ->where('amount', $normalizedAmount)
            ->where('discount', $normalizedDiscount)
            ->whereDate('paid_at', $paidAt)
            ->where('created_by', $createdBy);

        if ($matchInvoiceReference) {
            $query->when(
                $invoiceReference !== null && $invoiceReference !== '',
                fn (Builder $builder) => $builder->where('invoice_reference', $invoiceReference),
                fn (Builder $builder) => $builder->whereNull('invoice_reference'),
            );
        }

        return $query
            ->where('created_at', '>=', now()->subSeconds($this->dedupWindowSeconds()))
            ->first();
    }

    public function normalizeMoney(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
