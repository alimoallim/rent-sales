<?php

namespace App\Rules;

use App\Models\RentalBuilding;
use App\Models\SaleBuilding;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Building names must be unique across the whole system (rental + sales),
 * compared case-insensitively.
 */
class UniqueBuildingName implements ValidationRule
{
    public function __construct(
        private readonly ?int $ignoreRentalBuildingId = null,
        private readonly ?int $ignoreSaleBuildingId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $name = strtolower(trim((string) $value));

        if ($name === '') {
            return;
        }

        $rentalConflict = RentalBuilding::query()
            ->whereRaw('LOWER(name) = ?', [$name])
            ->when($this->ignoreRentalBuildingId, fn ($query) => $query->whereKeyNot($this->ignoreRentalBuildingId))
            ->exists();

        if ($rentalConflict) {
            $fail('A building with this name already exists in the rental module.');

            return;
        }

        $saleConflict = SaleBuilding::query()
            ->whereRaw('LOWER(name) = ?', [$name])
            ->when($this->ignoreSaleBuildingId, fn ($query) => $query->whereKeyNot($this->ignoreSaleBuildingId))
            ->exists();

        if ($saleConflict) {
            $fail('A building with this name already exists in the sales module.');
        }
    }
}
