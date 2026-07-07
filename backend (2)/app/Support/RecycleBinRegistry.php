<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\RentalBuilding;
use App\Models\RentalExpense;
use App\Models\RentalUnit;
use App\Models\SaleBuilding;
use App\Models\SalesExpense;
use App\Models\SaleUnit;
use App\Models\Shareholder;
use App\Models\ShareholderBill;
use App\Models\User;

class RecycleBinRegistry
{
    /**
     * @return array<string, array{model: class-string, label: string, module: string, name_column: string}>
     */
    public static function types(): array
    {
        return [
            'rental_buildings' => [
                'model' => RentalBuilding::class,
                'label' => 'Rental building',
                'module' => 'rental',
                'name_column' => 'name',
            ],
            'rental_units' => [
                'model' => RentalUnit::class,
                'label' => 'Rental unit',
                'module' => 'rental',
                'name_column' => 'house_number',
            ],
            'rental_expenses' => [
                'model' => RentalExpense::class,
                'label' => 'Rental expense',
                'module' => 'rental',
                'name_column' => 'name',
            ],
            'employees' => [
                'model' => Employee::class,
                'label' => 'Employee',
                'module' => 'rental',
                'name_column' => 'name',
            ],
            'payroll_entries' => [
                'model' => PayrollEntry::class,
                'label' => 'Payroll entry',
                'module' => 'rental',
                'name_column' => 'id',
            ],
            'shareholders' => [
                'model' => Shareholder::class,
                'label' => 'Shareholder',
                'module' => 'rental',
                'name_column' => 'name',
            ],
            'shareholder_bills' => [
                'model' => ShareholderBill::class,
                'label' => 'Shareholder bill',
                'module' => 'rental',
                'name_column' => 'id',
            ],
            'sale_buildings' => [
                'model' => SaleBuilding::class,
                'label' => 'Sale building',
                'module' => 'sales',
                'name_column' => 'name',
            ],
            'sale_units' => [
                'model' => SaleUnit::class,
                'label' => 'Sale unit',
                'module' => 'sales',
                'name_column' => 'house_number',
            ],
            'sales_expenses' => [
                'model' => SalesExpense::class,
                'label' => 'Sales expense',
                'module' => 'sales',
                'name_column' => 'name',
            ],
            'users' => [
                'model' => User::class,
                'label' => 'User',
                'module' => 'admin',
                'name_column' => 'name',
            ],
        ];
    }

    /**
     * @return array{model: class-string, label: string, module: string, name_column: string}|null
     */
    public static function resolve(string $type): ?array
    {
        return self::types()[$type] ?? null;
    }
}
