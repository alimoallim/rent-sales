<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('requires_water_metering')->default(false)->after('service_amount');
            $table->boolean('requires_electricity_metering')->default(false)->after('requires_water_metering');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['requires_water_metering', 'requires_electricity_metering']);
        });
    }
};
