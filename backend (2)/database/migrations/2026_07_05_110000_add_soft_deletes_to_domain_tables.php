<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every table whose records can be deleted through the API keeps its
     * rows via soft deletes so no data is ever permanently erased.
     *
     * @var list<string>
     */
    private array $tables = [
        'rental_buildings',
        'rental_units',
        'rental_expenses',
        'employees',
        'payroll_entries',
        'shareholders',
        'shareholder_bills',
        'sale_buildings',
        'sale_units',
        'sales_expenses',
        'users',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropSoftDeletes();
            });
        }
    }
};
