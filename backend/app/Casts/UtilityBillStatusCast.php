<?php

namespace App\Casts;

use App\Enums\ElectricityBillStatus;
use App\Enums\WaterBillStatus;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<WaterBillStatus|ElectricityBillStatus, string>
 */
class UtilityBillStatusCast implements CastsAttributes
{
    /**
     * @param  class-string<WaterBillStatus|ElectricityBillStatus>  $enumClass
     */
    public function __construct(private readonly string $enumClass) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): WaterBillStatus|ElectricityBillStatus
    {
        if ($value === 'pending') {
            return $this->enumClass::Recorded;
        }

        return $this->enumClass::from((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof WaterBillStatus || $value instanceof ElectricityBillStatus) {
            return $value->value;
        }

        if ($value === 'pending') {
            return $this->enumClass::Recorded->value;
        }

        return $this->enumClass::from((string) $value)->value;
    }
}
