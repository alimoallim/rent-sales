<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_receipt_sequences')) {
            Schema::create('payment_receipt_sequences', function (Blueprint $table) {
                $table->id();
                $table->string('module', 20);
                $table->unsignedBigInteger('scope_id');
                $table->unsignedBigInteger('last_number')->default(0);
                $table->timestamps();

                $table->unique(['module', 'scope_id']);
            });
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $this->deduplicatePaymentInvoiceReferences('rent_payments', 'rental_building_id');
        $this->deduplicatePaymentInvoiceReferences('sales_payments', 'sale_building_id');
        $this->deduplicateActiveTenantsPerUnit();

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS rent_payments_building_invoice_unique ON rent_payments (rental_building_id, invoice_reference) WHERE invoice_reference IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS sales_payments_building_invoice_unique ON sales_payments (sale_building_id, invoice_reference) WHERE invoice_reference IS NOT NULL');
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS tenants_one_active_per_unit ON tenants (rental_unit_id) WHERE status = 'active'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS tenants_one_active_per_unit');
            DB::statement('DROP INDEX IF EXISTS sales_payments_building_invoice_unique');
            DB::statement('DROP INDEX IF EXISTS rent_payments_building_invoice_unique');
        }

        Schema::dropIfExists('payment_receipt_sequences');
    }

    private function deduplicatePaymentInvoiceReferences(string $table, string $buildingColumn): void
    {
        DB::statement(<<<SQL
            UPDATE {$table} AS payment
            SET invoice_reference = payment.invoice_reference || '-D' || payment.id::text
            FROM (
                SELECT id
                FROM (
                    SELECT
                        id,
                        ROW_NUMBER() OVER (
                            PARTITION BY {$buildingColumn}, invoice_reference
                            ORDER BY id
                        ) AS row_number
                    FROM {$table}
                    WHERE invoice_reference IS NOT NULL
                ) ranked
                WHERE row_number > 1
            ) duplicates
            WHERE payment.id = duplicates.id
        SQL);
    }

    private function deduplicateActiveTenantsPerUnit(): void
    {
        DB::statement(<<<SQL
            UPDATE tenants AS tenant
            SET status = 'inactive'
            FROM (
                SELECT id
                FROM (
                    SELECT
                        id,
                        ROW_NUMBER() OVER (
                            PARTITION BY rental_unit_id
                            ORDER BY id
                        ) AS row_number
                    FROM tenants
                    WHERE status = 'active'
                ) ranked
                WHERE row_number > 1
            ) duplicates
            WHERE tenant.id = duplicates.id
        SQL);
    }
};
