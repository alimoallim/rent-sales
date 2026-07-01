<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rent_charges', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'billing_month', 'billing_year']);
        });

        Schema::table('rent_charges', function (Blueprint $table): void {
            $table->foreignId('tenant_water_bill_id')
                ->nullable()
                ->unique()
                ->after('purpose')
                ->constrained('tenant_water_bills')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rent_charges', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tenant_water_bill_id');
            $table->unique(['tenant_id', 'billing_month', 'billing_year']);
        });
    }
};
