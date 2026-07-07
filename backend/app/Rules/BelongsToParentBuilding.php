<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BelongsToParentBuilding implements ValidationRule
{
    public function __construct(
        private readonly string $parentTable,
        private readonly string $parentIdColumn,
        private readonly int $buildingId,
        private readonly string $buildingIdColumn,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $exists = \Illuminate\Support\Facades\DB::table($this->parentTable)
            ->where($this->parentIdColumn, $value)
            ->where($this->buildingIdColumn, $this->buildingId)
            ->exists();

        if (! $exists) {
            $fail('The selected :attribute does not belong to the chosen building.');
        }
    }
}
