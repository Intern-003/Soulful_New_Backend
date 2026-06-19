<?php
// database/migrations/2024_01_01_000001_update_products_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateProductsTable extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'tax_rate')) {
                $table->decimal('tax_rate', 5, 2)->default(18)->after('discount_price');
            }
            if (!Schema::hasColumn('products', 'shipping_mode')) {
                $table->enum('shipping_mode', ['vendor', 'marketplace'])->default('vendor')->after('tax_rate');
            }
            if (!Schema::hasColumn('products', 'shipping_charge')) {
                $table->decimal('shipping_charge', 10, 2)->nullable()->after('shipping_mode');
            }
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['tax_rate', 'shipping_mode', 'shipping_charge']);
        });
    }
}