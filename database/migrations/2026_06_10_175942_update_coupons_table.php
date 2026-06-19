<?php
// database/migrations/2026_06_10_175942_update_coupons_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateCouponsTable extends Migration
{
    public function up()
    {
        Schema::table('coupons', function (Blueprint $table) {
            // First, determine what column names exist
            $hasDiscountValue = Schema::hasColumn('coupons', 'discount_value');
            $hasValue = Schema::hasColumn('coupons', 'value');
            
            // Use the appropriate column name for AFTER clause
            $afterColumn = $hasDiscountValue ? 'discount_value' : ($hasValue ? 'value' : 'id');
            
            if (!Schema::hasColumn('coupons', 'funded_by')) {
                $table->enum('funded_by', ['admin', 'vendor', 'shared'])->default('admin')->after($afterColumn);
            }
            if (!Schema::hasColumn('coupons', 'vendor_share_percentage')) {
                $table->decimal('vendor_share_percentage', 5, 2)->nullable()->after('funded_by');
            }
            if (!Schema::hasColumn('coupons', 'admin_share_percentage')) {
                $table->decimal('admin_share_percentage', 5, 2)->nullable()->after('vendor_share_percentage');
            }
            if (!Schema::hasColumn('coupons', 'applies_to')) {
                $table->enum('applies_to', ['all', 'vendor', 'category', 'product'])->default('all')->after('admin_share_percentage');
            }
            if (!Schema::hasColumn('coupons', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('applies_to')->constrained('categories');
            }
            if (!Schema::hasColumn('coupons', 'product_id')) {
                $table->foreignId('product_id')->nullable()->after('category_id')->constrained('products');
            }
            if (!Schema::hasColumn('coupons', 'applicable_vendors')) {
                $table->json('applicable_vendors')->nullable()->after('product_id');
            }
        });
    }

    public function down()
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (Schema::hasColumn('coupons', 'category_id')) {
                $table->dropForeign(['category_id']);
            }
            if (Schema::hasColumn('coupons', 'product_id')) {
                $table->dropForeign(['product_id']);
            }
            
            $columnsToDrop = [
                'funded_by', 'vendor_share_percentage', 'admin_share_percentage', 
                'applies_to', 'category_id', 'product_id', 'applicable_vendors'
            ];
            
            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('coupons', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}