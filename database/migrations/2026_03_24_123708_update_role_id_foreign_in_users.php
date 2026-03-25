<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop existing role_id column (and any broken FK)
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role_id');
        });

        // Add new role_id column with correct FK
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')
                ->nullable()
                ->constrained('roles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Drop the new column
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });

        // Recreate old column (if needed)
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            // old FK or constraints can be ignored
        });
    }

};
