<?php

namespace App\Exceptions;

use RuntimeException;

class DuplicateRentChargeException extends RuntimeException
{
    public static function forPeriod(int $tenantId, int $month, int $year, string $purpose): self
    {
        return new self(
            "A {$purpose} charge already exists for tenant {$tenantId} in {$month}/{$year}. Duplicate billing is not allowed.",
        );
    }

    public static function fromBatchItem(int $batchItemId): self
    {
        return new self("Charge batch line {$batchItemId} has already been posted to a tenant balance.");
    }
}
