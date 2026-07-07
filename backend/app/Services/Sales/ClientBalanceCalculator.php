<?php

namespace App\Services\Sales;

use App\Enums\SalesPaymentStatus;
use App\Models\Client;
use App\Support\MoneyConfig;

class ClientBalanceCalculator
{
    public function calculate(Client|int $client, ?int $excludePaymentId = null): string
    {
        return $this->summary($client, $excludePaymentId)['balance'];
    }

    /**
     * Outstanding amount the client still owes before a new payment is applied.
     */
    public function amountOwed(Client|int $client, ?int $excludePaymentId = null): string
    {
        $balance = $this->calculate($client, $excludePaymentId);

        return bccomp($balance, '0', 2) > 0 ? $balance : '0.00';
    }

    /**
     * @return array{agreed_sale_price: string, deposit: string, payments_total: string, discounts_total: string, paid_total: string, balance: string, status: string, currency_code: string}
     */
    public function summary(Client|int $client, ?int $excludePaymentId = null): array
    {
        $clientModel = $client instanceof Client
            ? $client
            : Client::query()->findOrFail($client);

        $agreedPrice = bcadd((string) $clientModel->agreed_sale_price, '0', 2);
        $deposit = bcadd((string) $clientModel->deposit, '0', 2);

        $paymentsQuery = $clientModel->payments()->where('status', SalesPaymentStatus::Active);
        if ($excludePaymentId !== null) {
            $paymentsQuery->where('id', '!=', $excludePaymentId);
        }

        $payments = bcadd((string) (clone $paymentsQuery)->sum('amount'), '0', 2);

        $discounts = bcadd((string) (clone $paymentsQuery)->sum('discount'), '0', 2);

        $paidTotal = bcadd(bcadd($payments, $deposit, 2), $discounts, 2);
        $balance = bcsub($agreedPrice, $paidTotal, 2);

        return [
            'agreed_sale_price' => $agreedPrice,
            'deposit' => $deposit,
            'payments_total' => $payments,
            'discounts_total' => $discounts,
            'paid_total' => $paidTotal,
            'balance' => $balance,
            'status' => bccomp($balance, '0', 2) > 0 ? 'owes' : (bccomp($balance, '0', 2) < 0 ? 'credit' : 'paid_up'),
            'currency_code' => $clientModel->currency_code ?? MoneyConfig::salesCurrency(),
        ];
    }
}
