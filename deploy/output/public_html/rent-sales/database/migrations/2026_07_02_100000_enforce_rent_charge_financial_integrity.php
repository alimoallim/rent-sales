<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $this->removeDuplicateBillableCharges();

        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS rent_charges_unique_billable_period
            ON rent_charges (tenant_id, billing_month, billing_year, purpose)
            WHERE purpose IN ('Rent + service', 'Water', 'Electricity')
        ");

        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS rent_charges_unique_batch_item
            ON rent_charges (charge_batch_item_id)
            WHERE charge_batch_item_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS rent_charges_unique_billable_period');
        DB::statement('DROP INDEX IF EXISTS rent_charges_unique_batch_item');
    }

    private function removeDuplicateBillableCharges(): void
    {
        DB::statement("
            DELETE FROM rent_charges doomed
            USING rent_charges keeper
            WHERE doomed.id < keeper.id
              AND doomed.tenant_id = keeper.tenant_id
              AND doomed.billing_month = keeper.billing_month
              AND doomed.billing_year = keeper.billing_year
              AND doomed.purpose = keeper.purpose
              AND doomed.purpose IN ('Rent + service', 'Water', 'Electricity')
        ");
    }
};
