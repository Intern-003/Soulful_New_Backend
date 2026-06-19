<?php
// database/migrations/2024_01_01_000002_update_product_variants_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateProductVariantsTable extends Migration
{
    public function up()
    {
        Schema::table('product_variants', function (Blueprint $table) {
            if (!Schema::hasColumn('product_variants', 'tax_rate')) {
                $table->decimal('tax_rate', 5, 2)->nullable()->after('discount_price');
            }
            if (!Schema::hasColumn('product_variants', 'shipping_charge')) {
                $table->decimal('shipping_charge', 10, 2)->nullable()->after('tax_rate');
            }
            if (!Schema::hasColumn('product_variants', 'cost_price')) {
                $table->decimal('cost_price', 10, 2)->nullable()->after('shipping_charge');
            }
        });
    }

    public function down()
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['tax_rate', 'shipping_charge', 'cost_price']);
        });
    }
}