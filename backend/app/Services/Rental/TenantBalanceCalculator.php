<?php

namespace App\Services\Rental;

use App\Models\Tenant;

class TenantBalanceCalculator
{
    public function __construct(private readonly TenantBalanceBreakdownService $breakdownService) {}

    public function calculate(Tenant|int $tenant, ?int $excludePaymentId = null): string
    {
        return $this->breakdownService->totalDue($tenant, $excludePaymentId);
    }
}
