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
            $table->unsignedBigInteger('creator_id')->nullable()->after('vendor_id');

            // optional index for performance
            $table->index('creator_id');
             $table->foreignId('vendor_id')
            ->nullable()
            ->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('creator_id');
            $table->foreignId('vendor_id')
            ->nullable(false)
            ->change();
        });
    }
};
