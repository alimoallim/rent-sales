<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Unit numbers must be unique within their building, compared case-insensitively.
 */
class UniqueUnitNumber implements ValidationRule
{
    public function __construct(
        private readonly string $table,
        private readonly string $buildingColumn,
        private readonly mixed $buildingId,
        private readonly ?int $ignoreUnitId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $number = strtolower(trim((string) $value));
        $buildingId = is_numeric($this->buildingId) ? (int) $this->buildingId : null;

        if ($number === '' || $buildingId === null) {
            return;
        }

        $exists = DB::table($this->table)
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(house_number) = ?', [$number])
            ->where($this->buildingColumn, $buildingId)
            ->when($this->ignoreUnitId, fn ($query) => $query->where('id', '!=', $this->ignoreUnitId))
            ->exists();

        if ($exists) {
            $fail('A unit with this number already exists in the selected building.');
        }
    }
}
