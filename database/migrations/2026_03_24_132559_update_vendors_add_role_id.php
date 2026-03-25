<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            // Only add role_id if it doesn't exist
            if (!Schema::hasColumn('vendors', 'role_id')) {
                $table->foreignId('role_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('roles')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (Schema::hasColumn('vendors', 'role_id')) {
                $table->dropForeign(['role_id']);
                $table->dropColumn('role_id');
            }
        });
    }
};