<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropStatusConstraint('tenant_water_bills');
        $this->dropStatusConstraint('tenant_electricity_bills');

        DB::table('tenant_water_bills')
            ->where('status', 'pending')
            ->update(['status' => 'recorded']);

        DB::table('tenant_electricity_bills')
            ->where('status', 'pending')
            ->update(['status' => 'recorded']);

        $this->addStatusConstraint('tenant_water_bills', ['recorded', 'paid'], 'recorded');
        $this->addStatusConstraint('tenant_electricity_bills', ['recorded', 'paid'], 'recorded');
    }

    public function down(): void
    {
        $this->dropStatusConstraint('tenant_water_bills');
        $this->dropStatusConstraint('tenant_electricity_bills');

        DB::table('tenant_water_bills')
            ->where('status', 'recorded')
            ->whereNull('amount_paid')
            ->update(['status' => 'pending']);

        DB::table('tenant_electricity_bills')
            ->where('status', 'recorded')
            ->whereNull('amount_paid')
            ->update(['status' => 'pending']);

        $this->addStatusConstraint('tenant_water_bills', ['pending', 'paid'], 'pending');
        $this->addStatusConstraint('tenant_electricity_bills', ['pending', 'paid'], 'pending');
    }

    private function dropStatusConstraint(string $table): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_status_check");

            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->string('status')->change();
        });
    }

    /**
     * @param  list<string>  $allowed
     */
    private function addStatusConstraint(string $table, array $allowed, string $default): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $values = implode("', '", $allowed);
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_status_check CHECK (status IN ('{$values}'))");
            DB::statement("ALTER TABLE {$table} ALTER COLUMN status SET DEFAULT '{$default}'");

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($allowed, $default): void {
            $blueprint->enum('status', $allowed)->default($default)->change();
        });
    }
};
