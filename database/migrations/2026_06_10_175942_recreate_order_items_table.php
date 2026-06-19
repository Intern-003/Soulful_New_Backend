<?php
// database/migrations/2026_06_10_175942_recreate_order_items_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RecreateOrderItemsTable extends Migration
{
    public function up()
    {
        // First, drop tables that have foreign keys to order_items
        Schema::disableForeignKeyConstraints();
        
        // Drop dependent tables first
        if (Schema::hasTable('vendor_transactions')) {
            Schema::dropIfExists('vendor_transactions');
        }
        
        if (Schema::hasTable('shipments')) {
            Schema::dropIfExists('shipments');
        }
        
        if (Schema::hasTable('order_status_histories')) {
            Schema::dropIfExists('order_status_histories');
        }
        
        // Now drop order_items
        if (Schema::hasTable('order_items')) {
            Schema::dropIfExists('order_items');
        }
        
        // Create new order_items table
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants');
            $table->foreignId('vendor_id')->constrained();
            $table->string('product_name');
            $table->string('product_sku');
            $table->json('product_attributes')->nullable();
            $table->integer('quantity');
            
            // Pricing snapshot (CRITICAL)
            $table->decimal('mrp', 12, 2);
            $table->decimal('selling_price', 12, 2);
            
            // Coupon snapshot
            $table->decimal('coupon_discount', 12, 2)->default(0);
            $table->enum('coupon_funded_by', ['admin', 'vendor', 'shared'])->nullable();
            $table->decimal('vendor_coupon_share', 12, 2)->default(0);
            $table->decimal('admin_coupon_share', 12, 2)->default(0);
            
            // Tax snapshot
            $table->decimal('tax_rate', 5, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->json('tax_breakdown')->nullable();
            
            // Shipping snapshot
            $table->enum('shipping_mode', ['vendor', 'marketplace'])->default('vendor');
            $table->decimal('shipping_charge', 10, 2)->default(0);
            
            // Commission snapshot
            $table->enum('commission_type', ['fixed', 'percentage']);
            $table->decimal('commission_rate', 12, 2);
            $table->decimal('commission_amount', 12, 2);
            
            // Final calculations
            $table->decimal('final_price', 12, 2);
            $table->decimal('vendor_payout', 12, 2);
            
            // Settlement tracking
            $table->enum('settlement_status', ['pending', 'settled', 'failed'])->default('pending');
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('shipment_id')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('eligible_for_settlement_at')->nullable();
            $table->boolean('return_requested')->default(false);
            $table->text('return_reason')->nullable();
            $table->timestamp('return_requested_at')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['order_id', 'vendor_id']);
            $table->index(['vendor_id', 'settlement_status']);
            $table->index(['order_id', 'shipment_id']);
        });
        
        // Recreate vendor_transactions table
        Schema::create('vendor_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_wallet_id')->constrained();
            $table->foreignId('vendor_id')->constrained();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->onDelete('set null');
            $table->decimal('amount', 15, 2);
            $table->decimal('coupon_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('shipping_amount', 12, 2)->default(0);
            $table->decimal('commission', 15, 2)->default(0);
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->decimal('net_amount', 15, 2);
            $table->enum('type', ['credit', 'debit']);
            $table->string('source')->default('order');
            $table->text('description')->nullable();
            $table->string('reference_id')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->timestamps();
        });
        
        // Recreate shipments table
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->onDelete('set null');
            $table->foreignId('vendor_id')->constrained();
            $table->string('carrier');
            $table->string('tracking_number');
            $table->enum('status', ['pending', 'shipped', 'delivered', 'cancelled'])->default('pending');
            $table->enum('shipping_mode', ['vendor', 'marketplace'])->default('vendor');
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->decimal('courier_cost', 10, 2)->default(0);
            $table->decimal('profit', 10, 2)->default(0);
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('estimated_delivery')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
        
        // Recreate order_status_histories table
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->onDelete('cascade');
            $table->string('status');
            $table->text('note')->nullable();
            $table->timestamps();
        });
        
        Schema::enableForeignKeyConstraints();
    }

    public function down()
    {
        Schema::disableForeignKeyConstraints();
        
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('vendor_transactions');
        Schema::dropIfExists('order_items');
        
        Schema::enableForeignKeyConstraints();
    }
}