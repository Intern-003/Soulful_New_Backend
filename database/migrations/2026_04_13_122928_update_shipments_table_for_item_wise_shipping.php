<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {

            // ✅ Add order_item_id (nullable for full-order shipment)
            $table->unsignedBigInteger('order_item_id')->nullable()->after('order_id');

            // ✅ Add creator_id (for non-vendor sellers)
            $table->unsignedBigInteger('creator_id')->nullable()->after('vendor_id');

            // ✅ Indexes (important for performance)
            $table->index('order_item_id');
            $table->index('vendor_id');
            $table->index('creator_id');

            // ✅ Prevent duplicate shipment per item
            $table->unique('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {

            $table->dropUnique(['order_item_id']);

            $table->dropIndex(['order_item_id']);
            $table->dropIndex(['vendor_id']);
            $table->dropIndex(['creator_id']);

            $table->dropColumn('order_item_id');
            $table->dropColumn('creator_id');
        });
    }

};
