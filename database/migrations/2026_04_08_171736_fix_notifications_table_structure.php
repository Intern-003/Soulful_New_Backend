<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {

            // ❌ Drop custom columns
            if (Schema::hasColumn('notifications', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }

            if (Schema::hasColumn('notifications', 'title')) {
                $table->dropColumn('title');
            }

            if (Schema::hasColumn('notifications', 'message')) {
                $table->dropColumn('message');
            }

            // ❌ Drop wrong type column (nullable one)
            if (Schema::hasColumn('notifications', 'type')) {
                $table->dropColumn('type');
            }
        });

        Schema::table('notifications', function (Blueprint $table) {

            // ✅ Add Laravel required columns ONLY if missing

            if (!Schema::hasColumn('notifications', 'notifiable_id')) {
                $table->unsignedBigInteger('notifiable_id');
            }

            if (!Schema::hasColumn('notifications', 'notifiable_type')) {
                $table->string('notifiable_type');
            }

            if (!Schema::hasColumn('notifications', 'data')) {
                $table->text('data');
            }

            // re-add type correctly
            $table->string('type');
        });
    }

    public function down(): void
    {
        // optional rollback (can skip for now)
    }
};

   
