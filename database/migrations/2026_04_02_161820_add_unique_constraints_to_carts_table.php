<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            // ✅ Add unique constraints
            $table->unique('user_id');
            $table->unique('guest_token');

            // ✅ Optional (performance indexes)
            $table->index('user_id');
            $table->index('guest_token');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            // ❌ Drop constraints if rollback
            $table->dropUnique(['user_id']);
            $table->dropUnique(['guest_token']);

            $table->dropIndex(['user_id']);
            $table->dropIndex(['guest_token']);
        });
    }
};