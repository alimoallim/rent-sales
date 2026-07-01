<?php

namespace App\Services\Sales;

use App\Enums\SalesPaymentStatus;
use App\Models\Client;

class ClientBalanceCalculator
{
    public function calculate(Client|int $client): string
    {
        $clientModel = $client instanceof Client
            ? $client
            : Client::query()->findOrFail($client);

        $agreedPrice = (string) $clientModel->agreed_sale_price;
        $deposit = (string) $clientModel->deposit;

        $payments = (string) $clientModel->payments()
            ->where('status', SalesPaymentStatus::Active)
            ->sum('amount');

        $discounts = (string) $clientModel->payments()
            ->where('status', SalesPaymentStatus::Active)
            ->sum('discount');

        $paidTotal = bcadd(bcadd($payments, $deposit, 2), $discounts, 2);

        return bcsub($agreedPrice, $paidTotal, 2);
    }

    /**
     * @return array{agreed_sale_price: string, deposit: string, payments_total: string, discounts_total: string, paid_total: string, balance: string, status: string}
     */
    public function summary(Client|int $client): array
    {
        $clientModel = $client instanceof Client
            ? $client
            : Client::query()->findOrFail($client);

        $agreedPrice = bcadd((string) $clientModel->agreed_sale_price, '0', 2);
        $deposit = bcadd((string) $clientModel->deposit, '0', 2);

        $payments = bcadd((string) $clientModel->payments()
            ->where('status', SalesPaymentStatus::Active)
            ->sum('amount'), '0', 2);

        $discounts = bcadd((string) $clientModel->payments()
            ->where('status', SalesPaymentStatus::Active)
            ->sum('discount'), '0', 2);

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
        ];
    }
}
