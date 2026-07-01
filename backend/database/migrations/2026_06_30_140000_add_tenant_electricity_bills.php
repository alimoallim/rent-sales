<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_electricity_bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('rental_building_id')->constrained('rental_buildings')->restrictOnDelete();
            $table->unsignedTinyInteger('billing_month');
            $table->unsignedSmallInteger('billing_year');
            $table->unsignedInteger('previous_reading');
            $table->unsignedInteger('current_reading');
            $table->unsignedInteger('consumption');
            $table->decimal('rate', 14, 2)->default(0);
            $table->decimal('fixed_fee', 14, 2)->default(0);
            $table->decimal('amount', 14, 2);
            $table->decimal('amount_paid', 14, 2)->nullable();
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->string('remark', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'billing_month', 'billing_year']);
            $table->index(['rental_building_id', 'billing_year', 'billing_month']);
        });

        Schema::table('rent_charges', function (Blueprint $table): void {
            $table->foreignId('tenant_electricity_bill_id')
                ->nullable()
                ->unique()
                ->after('tenant_water_bill_id')
                ->constrained('tenant_electricity_bills')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rent_charges', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tenant_electricity_bill_id');
        });

        Schema::dropIfExists('tenant_electricity_bills');
    }
};
