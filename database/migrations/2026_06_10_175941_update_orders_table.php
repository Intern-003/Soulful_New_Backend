<?php
// database/migrations/2026_06_10_175941_update_orders_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateOrdersTable extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            // First, rename columns if they exist
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
            
            // Add new columns without AFTER clause to avoid missing column errors
            if (!Schema::hasColumn('orders', 'platform_coupon_discount')) {
                $table->decimal('platform_coupon_discount', 12, 2)->default(0);
            }
            if (!Schema::hasColumn('orders', 'vendor_coupon_discount')) {
                $table->decimal('vendor_coupon_discount', 12, 2)->default(0);
            }
            if (!Schema::hasColumn('orders', 'settlement_status')) {
                $table->enum('settlement_status', ['pending', 'partially_settled', 'settled'])->default('pending');
            }
            if (!Schema::hasColumn('orders', 'settled_at')) {
                $table->timestamp('settled_at')->nullable();
            }
            if (!Schema::hasColumn('orders', 'shipped_at')) {
                $table->timestamp('shipped_at')->nullable();
            }
            if (!Schema::hasColumn('orders', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable();
            }
            if (!Schema::hasColumn('orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable();
            }
            if (!Schema::hasColumn('orders', 'return_requested')) {
                $table->boolean('return_requested')->default(false);
            }
            if (!Schema::hasColumn('orders', 'exchange_requested')) {
                $table->boolean('exchange_requested')->default(false);
            }
            if (!Schema::hasColumn('orders', 'return_reason')) {
                $table->text('return_reason')->nullable();
            }
            if (!Schema::hasColumn('orders', 'exchange_reason')) {
                $table->text('exchange_reason')->nullable();
            }
            if (!Schema::hasColumn('orders', 'exchange_product_id')) {
                $table->foreignId('exchange_product_id')->nullable()->constrained('products');
            }
            if (!Schema::hasColumn('orders', 'exchange_variant_id')) {
                $table->foreignId('exchange_variant_id')->nullable()->constrained('product_variants');
            }
            if (!Schema::hasColumn('orders', 'return_requested_at')) {
                $table->timestamp('return_requested_at')->nullable();
            }
            if (!Schema::hasColumn('orders', 'exchange_requested_at')) {
                $table->timestamp('exchange_requested_at')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            // Drop columns that were added
            $table->dropColumn([
                'platform_coupon_discount', 'vendor_coupon_discount', 'settlement_status', 'settled_at',
                'shipped_at', 'delivered_at', 'cancelled_at', 'return_requested',
                'exchange_requested', 'return_reason', 'exchange_reason',
                'exchange_product_id', 'exchange_variant_id', 'return_requested_at',
                'exchange_requested_at'
            ]);
            
            // Rename back if needed
            if (Schema::hasColumn('orders', 'coupon_discount') && !Schema::hasColumn('orders', 'discount_amount')) {
                $table->renameColumn('coupon_discount', 'discount_amount');
            }
            if (Schema::hasColumn('orders', 'tax_total') && !Schema::hasColumn('orders', 'tax_amount')) {
                $table->renameColumn('tax_total', 'tax_amount');
            }
            if (Schema::hasColumn('orders', 'shipping_total') && !Schema::hasColumn('orders', 'shipping_cost')) {
                $table->renameColumn('shipping_total', 'shipping_cost');
            }
            if (Schema::hasColumn('orders', 'grand_total') && !Schema::hasColumn('orders', 'total')) {
                $table->renameColumn('grand_total', 'total');
            }
        });
    }
}