<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 🔥 remove avatar column
            $table->dropColumn('avatar');

            // 🔥 make role_id nullable
            $table->foreignId('role_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar')->nullable();
            $table->foreignId('role_id')->nullable(false)->change();
        });
    }

};
