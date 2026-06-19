<?php
// database/migrations/2024_01_01_000003_update_cart_items_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateCartItemsTable extends Migration
{
    public function up()
    {
        Schema::table('cart_items', function (Blueprint $table) {
            if (!Schema::hasColumn('cart_items', 'vendor_id')) {
                $table->foreignId('vendor_id')->after('cart_id')->constrained('vendors');
            }
            if (!Schema::hasColumn('cart_items', 'mrp')) {
                $table->decimal('mrp', 12, 2)->after('vendor_id');
            }
            if (!Schema::hasColumn('cart_items', 'selling_price')) {
                $table->decimal('selling_price', 12, 2)->after('mrp');
            }
            if (!Schema::hasColumn('cart_items', 'tax_rate')) {
                $table->decimal('tax_rate', 5, 2)->default(18)->after('selling_price');
            }
            if (!Schema::hasColumn('cart_items', 'estimated_shipping')) {
                $table->decimal('estimated_shipping', 10, 2)->default(0)->after('tax_rate');
            }
            if (!Schema::hasColumn('cart_items', 'shipping_mode')) {
                $table->enum('shipping_mode', ['vendor', 'marketplace'])->default('vendor')->after('estimated_shipping');
            }
        });
    }

    public function down()
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropColumn(['vendor_id', 'mrp', 'selling_price', 'tax_rate', 'estimated_shipping', 'shipping_mode']);
        });
    }
}