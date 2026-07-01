<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charge_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rental_building_id')->constrained('rental_buildings')->restrictOnDelete();
            $table->unsignedTinyInteger('billing_month');
            $table->unsignedSmallInteger('billing_year');
            $table->enum('status', ['draft', 'partially_approved', 'locked'])->default('draft');
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('generated_at');
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['rental_building_id', 'billing_month', 'billing_year']);
        });

        Schema::create('charge_batch_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('charge_batch_id')->constrained('charge_batches')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->enum('charge_type', ['rent', 'service', 'water', 'electricity']);
            $table->decimal('amount', 14, 2)->nullable();
            $table->decimal('source_amount', 14, 2)->nullable();
            $table->enum('item_status', ['draft', 'pending', 'approved', 'excluded'])->default('draft');
            $table->string('pending_reason', 100)->nullable();
            $table->string('exclusion_reason', 500)->nullable();
            $table->boolean('manually_adjusted')->default(false);
            $table->foreignId('adjusted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('adjusted_at')->nullable();
            $table->string('adjustment_note', 500)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('tenant_water_bill_id')->nullable()->constrained('tenant_water_bills')->nullOnDelete();
            $table->foreignId('tenant_electricity_bill_id')->nullable()->constrained('tenant_electricity_bills')->nullOnDelete();
            $table->timestamps();

            $table->unique(['charge_batch_id', 'tenant_id', 'charge_type']);
            $table->index(['charge_batch_id', 'tenant_id']);
        });

        Schema::table('rent_charges', function (Blueprint $table): void {
            $table->foreignId('charge_batch_item_id')
                ->nullable()
                ->after('tenant_electricity_bill_id')
                ->constrained('charge_batch_items')
                ->nullOnDelete();
        });

        Schema::create('charge_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('rental_building_id')->constrained('rental_buildings')->restrictOnDelete();
            $table->unsignedTinyInteger('billing_month');
            $table->unsignedSmallInteger('billing_year');
            $table->enum('charge_type', ['rent', 'service', 'water', 'electricity', 'credit']);
            $table->decimal('amount', 14, 2);
            $table->string('reason', 500);
            $table->foreignId('rent_charge_id')->nullable()->constrained('rent_charges')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at');
            $table->timestamps();

            $table->index(['tenant_id', 'billing_year', 'billing_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_adjustments');

        Schema::table('rent_charges', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('charge_batch_item_id');
        });

        Schema::dropIfExists('charge_batch_items');
        Schema::dropIfExists('charge_batches');
    }
};
