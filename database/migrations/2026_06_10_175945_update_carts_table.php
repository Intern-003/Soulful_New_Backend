<?php
// database/migrations/2024_01_01_000004_update_carts_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateCartsTable extends Migration
{
    public function up()
    {
        Schema::table('carts', function (Blueprint $table) {
            if (!Schema::hasColumn('carts', 'coupon_id')) {
                $table->foreignId('coupon_id')->nullable()->after('user_id')->constrained('coupons');
            }
            if (!Schema::hasColumn('carts', 'coupon_discount')) {
                $table->decimal('coupon_discount', 12, 2)->default(0)->after('coupon_id');
            }
            if (!Schema::hasColumn('carts', 'platform_coupon_discount')) {
                $table->decimal('platform_coupon_discount', 12, 2)->default(0)->after('coupon_discount');
            }
            if (!Schema::hasColumn('carts', 'vendor_coupon_discount')) {
                $table->decimal('vendor_coupon_discount', 12, 2)->default(0)->after('platform_coupon_discount');
            }
            if (!Schema::hasColumn('carts', 'subtotal')) {
                $table->decimal('subtotal', 12, 2)->default(0)->after('vendor_coupon_discount');
            }
            if (!Schema::hasColumn('carts', 'shipping_total')) {
                $table->decimal('shipping_total', 12, 2)->default(0)->after('subtotal');
            }
            if (!Schema::hasColumn('carts', 'tax_total')) {
                $table->decimal('tax_total', 12, 2)->default(0)->after('shipping_total');
            }
            if (!Schema::hasColumn('carts', 'grand_total')) {
                $table->decimal('grand_total', 12, 2)->default(0)->after('tax_total');
            }
            if (!Schema::hasColumn('carts', 'coupon_data')) {
                $table->json('coupon_data')->nullable()->after('grand_total');
            }
        });
    }

    public function down()
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropForeign(['coupon_id']);
            $table->dropColumn([
                'coupon_id', 'coupon_discount', 'platform_coupon_discount',
                'vendor_coupon_discount', 'subtotal', 'shipping_total',
                'tax_total', 'grand_total', 'coupon_data'
            ]);
        });
    }
}