<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_buildings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->string('name', 200);
            $table->timestamps();
        });

        Schema::create('rental_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('rental_building_id')->constrained('rental_buildings')->cascadeOnDelete();
            $table->string('house_number', 50);
            $table->string('floor', 50);
            $table->text('description');
            $table->decimal('monthly_rent', 14, 2)->default(0);
            $table->enum('status', ['vacant', 'occupied'])->default('vacant');
            $table->timestamps();

            $table->index(['rental_building_id', 'status']);
        });

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('rental_building_id')->constrained('rental_buildings')->restrictOnDelete();
            $table->foreignId('rental_unit_id')->constrained('rental_units')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('phone', 30);
            $table->string('gender', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('passport_or_id', 50)->nullable();
            $table->decimal('deposit', 14, 2)->default(0);
            $table->decimal('service_amount', 14, 2)->default(0);
            $table->string('next_of_kin_name', 100)->nullable();
            $table->string('next_of_kin_address', 200)->nullable();
            $table->string('next_of_kin_id', 50)->nullable();
            $table->string('next_of_kin_phone', 30)->nullable();
            $table->date('start_date')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('rental_building_id');
        });

        Schema::create('tenant_move_outs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('rental_building_id')->constrained('rental_buildings')->restrictOnDelete();
            $table->foreignId('rental_unit_id')->constrained('rental_units')->restrictOnDelete();
            $table->decimal('refund_amount', 14, 2)->default(0);
            $table->string('reason', 500);
            $table->date('moved_out_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('rent_charges', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('rental_unit_id')->constrained('rental_units')->restrictOnDelete();
            $table->foreignId('rental_building_id')->constrained('rental_buildings')->restrictOnDelete();
            $table->unsignedTinyInteger('billing_month');
            $table->unsignedSmallInteger('billing_year');
            $table->decimal('rent_amount', 14, 2)->default(0);
            $table->decimal('service_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('purpose', 50)->default('Rent + service');
            $table->timestamp('charged_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'billing_month', 'billing_year']);
            $table->index(['rental_building_id', 'billing_year', 'billing_month']);
        });

        Schema::create('rent_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('rental_building_id')->constrained('rental_buildings')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->decimal('discount', 14, 2)->default(0);
            $table->string('invoice_reference', 50)->nullable();
            $table->timestamp('paid_at');
            $table->enum('status', ['active', 'voided'])->default('active');
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['rental_building_id', 'paid_at']);
        });

        Schema::create('tenant_water_bills', function (Blueprint $table) {
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

        Schema::create('building_water_utility_bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('rental_building_id')->constrained('rental_buildings')->cascadeOnDelete();
            $table->unsignedTinyInteger('billing_month');
            $table->unsignedSmallInteger('billing_year');
            $table->decimal('amount', 14, 2);
            $table->string('remark', 500)->nullable();
            $table->date('billed_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['rental_building_id', 'billing_month', 'billing_year'], 'building_water_utility_period_unique');
        });

        Schema::create('building_electricity_bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('rental_building_id')->constrained('rental_buildings')->cascadeOnDelete();
            $table->unsignedTinyInteger('billing_month');
            $table->unsignedSmallInteger('billing_year');
            $table->decimal('amount', 14, 2);
            $table->string('remark', 500)->nullable();
            $table->date('billed_at');
            $table->timestamps();

            $table->index(['rental_building_id', 'billing_year', 'billing_month']);
        });

        Schema::create('rental_expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('rental_building_id')->constrained('rental_buildings')->cascadeOnDelete();
            $table->string('name', 200);
            $table->decimal('amount', 14, 2);
            $table->string('description', 500)->nullable();
            $table->timestamp('expense_date');
            $table->timestamps();

            $table->index(['rental_building_id', 'expense_date']);
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('rental_building_id')->nullable()->constrained('rental_buildings')->nullOnDelete();
            $table->string('name', 100);
            $table->string('address', 200)->nullable();
            $table->decimal('salary', 14, 2)->default(0);
            $table->string('phone', 30)->nullable();
            $table->string('position', 100);
            $table->enum('status', ['current', 'former'])->default('current');
            $table->timestamps();
        });

        Schema::create('payroll_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('rental_building_id')->constrained('rental_buildings')->restrictOnDelete();
            $table->unsignedTinyInteger('billing_month');
            $table->unsignedSmallInteger('billing_year');
            $table->decimal('salary_amount', 14, 2);
            $table->timestamp('paid_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['rental_building_id', 'billing_year', 'billing_month']);
        });

        Schema::create('shareholders', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->string('name', 100);
            $table->string('phone', 40)->nullable();
            $table->string('address', 200)->nullable();
            $table->timestamps();
        });

        Schema::create('shareholder_bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('shareholder_id')->constrained('shareholders')->restrictOnDelete();
            $table->foreignId('rental_building_id')->constrained('rental_buildings')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('remark', 500)->nullable();
            $table->date('bill_date');
            $table->timestamps();

            $table->index(['rental_building_id', 'bill_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shareholder_bills');
        Schema::dropIfExists('shareholders');
        Schema::dropIfExists('payroll_entries');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('rental_expenses');
        Schema::dropIfExists('building_electricity_bills');
        Schema::dropIfExists('building_water_utility_bills');
        Schema::dropIfExists('tenant_water_bills');
        Schema::dropIfExists('rent_payments');
        Schema::dropIfExists('rent_charges');
        Schema::dropIfExists('tenant_move_outs');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('rental_units');
        Schema::dropIfExists('rental_buildings');
    }
};
