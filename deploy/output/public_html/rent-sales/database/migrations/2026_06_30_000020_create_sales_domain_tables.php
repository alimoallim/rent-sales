<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_buildings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->string('name', 200);
            $table->timestamps();
        });

        Schema::create('sale_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('sale_building_id')->constrained('sale_buildings')->cascadeOnDelete();
            $table->string('house_number', 50);
            $table->string('floor', 50);
            $table->text('description');
            $table->decimal('list_price', 14, 2)->default(0);
            $table->enum('status', ['available', 'sold'])->default('available');
            $table->timestamps();

            $table->index(['sale_building_id', 'status']);
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('sale_building_id')->constrained('sale_buildings')->restrictOnDelete();
            $table->foreignId('sale_unit_id')->constrained('sale_units')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('phone', 30);
            $table->string('gender', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('passport_or_id', 50)->nullable();
            $table->decimal('agreed_sale_price', 14, 2);
            $table->string('voucher_number', 50)->nullable();
            $table->decimal('deposit', 14, 2)->default(0);
            $table->string('next_of_kin_name', 100)->nullable();
            $table->string('next_of_kin_address', 200)->nullable();
            $table->string('next_of_kin_id', 50)->nullable();
            $table->string('next_of_kin_phone', 30)->nullable();
            $table->date('registration_date')->nullable();
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('sale_building_id');
        });

        Schema::create('sales_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('sale_building_id')->constrained('sale_buildings')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->decimal('discount', 14, 2)->default(0);
            $table->string('invoice_reference', 50)->nullable();
            $table->string('bank', 100)->nullable();
            $table->string('remark', 200)->nullable();
            $table->timestamp('paid_at');
            $table->enum('status', ['active', 'cancelled'])->default('active');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['sale_building_id', 'paid_at']);
        });

        Schema::create('sales_expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('sale_building_id')->constrained('sale_buildings')->cascadeOnDelete();
            $table->string('name', 200);
            $table->decimal('amount', 14, 2);
            $table->string('description', 500)->nullable();
            $table->timestamp('expense_date');
            $table->timestamps();

            $table->index(['sale_building_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_expenses');
        Schema::dropIfExists('sales_payments');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('sale_units');
        Schema::dropIfExists('sale_buildings');
    }
};
