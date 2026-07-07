<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropUnique(['email']);
        });

        if (DB::getDriverName() !== 'pgsql') {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('username');
                $table->unique('email');
            });

            return;
        }

        DB::statement('CREATE UNIQUE INDEX users_username_unique_active ON users (username) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX users_email_unique_active ON users (email) WHERE deleted_at IS NULL AND email IS NOT NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS users_email_unique_active');
            DB::statement('DROP INDEX IF EXISTS users_username_unique_active');
        }

        Schema::table('users', function (Blueprint $table) {
            if (DB::getDriverName() !== 'pgsql') {
                $table->dropUnique(['username']);
                $table->dropUnique(['email']);
            }

            $table->unique('username');
            $table->unique('email');
        });
    }
};
