<?php
// database/migrations/2024_01_01_000005_update_orders_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateOrdersTableFinal extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            // Rename columns if they exist
            if (Schema::hasColumn('orders', 'discount_amount') && !Schema::hasColumn('orders', 'coupon_discount')) {
                $table->renameColumn('discount_amount', 'coupon_discount');
            }
            if (Schema::hasColumn('orders', 'tax_amount') && !Schema::hasColumn('orders', 'tax_total')) {
                $table->renameColumn('tax_amount', 'tax_total');
            }
            if (Schema::hasColumn('orders', 'shipping_cost') && !Schema::hasColumn('orders', 'shipping_total')) {
                $table->renameColumn('shipping_cost', 'shipping_total');
            }
            if (Schema::hasColumn('orders', 'total') && !Schema::hasColumn('orders', 'grand_total')) {
                $table->renameColumn('total', 'grand_total');
            }
            
            // Add new columns
            if (!Schema::hasColumn('orders', 'platform_coupon_discount')) {
                $table->decimal('platform_coupon_discount', 12, 2)->default(0)->after('coupon_discount');
            }
            if (!Schema::hasColumn('orders', 'vendor_coupon_discount')) {
                $table->decimal('vendor_coupon_discount', 12, 2)->default(0)->after('platform_coupon_discount');
            }
            if (!Schema::hasColumn('orders', 'settlement_status')) {
                $table->enum('settlement_status', ['pending', 'partially_settled', 'settled'])->default('pending')->after('payment_status');
            }
            if (!Schema::hasColumn('orders', 'settled_at')) {
                $table->timestamp('settled_at')->nullable()->after('settlement_status');
            }
            if (!Schema::hasColumn('orders', 'shipped_at')) {
                $table->timestamp('shipped_at')->nullable()->after('settled_at');
            }
            if (!Schema::hasColumn('orders', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('shipped_at');
            }
            if (!Schema::hasColumn('orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('delivered_at');
            }
            if (!Schema::hasColumn('orders', 'return_requested')) {
                $table->boolean('return_requested')->default(false)->after('cancelled_at');
            }
            if (!Schema::hasColumn('orders', 'exchange_requested')) {
                $table->boolean('exchange_requested')->default(false)->after('return_requested');
            }
            if (!Schema::hasColumn('orders', 'return_reason')) {
                $table->text('return_reason')->nullable()->after('exchange_requested');
            }
            if (!Schema::hasColumn('orders', 'exchange_reason')) {
                $table->text('exchange_reason')->nullable()->after('return_reason');
            }
            if (!Schema::hasColumn('orders', 'exchange_product_id')) {
                $table->foreignId('exchange_product_id')->nullable()->after('exchange_reason')->constrained('products');
            }
            if (!Schema::hasColumn('orders', 'exchange_variant_id')) {
                $table->foreignId('exchange_variant_id')->nullable()->after('exchange_product_id')->constrained('product_variants');
            }
            if (!Schema::hasColumn('orders', 'return_requested_at')) {
                $table->timestamp('return_requested_at')->nullable()->after('exchange_variant_id');
            }
            if (!Schema::hasColumn('orders', 'exchange_requested_at')) {
                $table->timestamp('exchange_requested_at')->nullable()->after('return_requested_at');
            }
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('coupon_discount', 'discount_amount');
            $table->renameColumn('tax_total', 'tax_amount');
            $table->renameColumn('shipping_total', 'shipping_cost');
            $table->renameColumn('grand_total', 'total');
            
            $table->dropColumn([
                'platform_coupon_discount', 'vendor_coupon_discount', 'settlement_status', 'settled_at',
                'shipped_at', 'delivered_at', 'cancelled_at', 'return_requested',
                'exchange_requested', 'return_reason', 'exchange_reason',
                'exchange_product_id', 'exchange_variant_id', 'return_requested_at',
                'exchange_requested_at'
            ]);
        });
    }
}