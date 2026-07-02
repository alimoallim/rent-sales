<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $salesCurrency = (string) config('money.sales.currency', 'USD');

        foreach (['clients', 'sales_payments', 'sales_expenses', 'sale_units'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->char('currency_code', 3)->default('USD')->after('id');
            });

            DB::table($table)->update(['currency_code' => $salesCurrency]);
        }

        DB::statement("ALTER TABLE clients ADD CONSTRAINT clients_currency_code_check CHECK (currency_code = 'USD')");
        DB::statement("ALTER TABLE sales_payments ADD CONSTRAINT sales_payments_currency_code_check CHECK (currency_code = 'USD')");
        DB::statement("ALTER TABLE sales_expenses ADD CONSTRAINT sales_expenses_currency_code_check CHECK (currency_code = 'USD')");
        DB::statement("ALTER TABLE sale_units ADD CONSTRAINT sale_units_currency_code_check CHECK (currency_code = 'USD')");
    }

    public function down(): void
    {
        foreach (['clients', 'sales_payments', 'sales_expenses', 'sale_units'] as $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_currency_code_check");
            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn('currency_code');
            });
        }
    }
};
