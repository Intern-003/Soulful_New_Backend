<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // 1. Make vendor_id nullable
            $table->unsignedBigInteger('vendor_id')->nullable()->change();
            
            // 2. Add creator_id column (references user_id from products table)
            $table->unsignedBigInteger('creator_id')->nullable()->after('vendor_id');
            
            // Add foreign key constraint for creator_id (optional but recommended)
            $table->foreign('creator_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['creator_id']);
            
            // Drop creator_id column
            $table->dropColumn('creator_id');
            
            // Make vendor_id not nullable again
            $table->unsignedBigInteger('vendor_id')->nullable(false)->change();
        });
    }
};