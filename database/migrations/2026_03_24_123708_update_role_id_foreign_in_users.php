<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ✅ Step 1: Drop foreign key safely (if exists)
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['role_id']);
            });
        } catch (\Exception $e) {
            // fallback: drop by constraint name if needed
            try {
                DB::statement('ALTER TABLE users DROP FOREIGN KEY users_role_id_foreign');
            } catch (\Exception $e) {}
        }

        // ✅ Step 2: Drop column safely
        if (Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role_id');
            });
        }

        // ✅ Step 3: Recreate column with correct FK
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')
                ->nullable()
                ->constrained('roles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // ✅ Step 1: Drop FK
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['role_id']);
            });
        } catch (\Exception $e) {
            try {
                DB::statement('ALTER TABLE users DROP FOREIGN KEY users_role_id_foreign');
            } catch (\Exception $e) {}
        }

        // ✅ Step 2: Drop column
        if (Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role_id');
            });
        }

        // ✅ Step 3: Recreate basic column (no FK)
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable();
        });
    }
};