<?php
// database/migrations/2024_01_01_000009_update_vendor_transactions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateVendorTransactionsTable extends Migration
{
    public function up()
    {
        Schema::table('vendor_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('vendor_transactions', 'coupon_amount')) {
                $table->decimal('coupon_amount', 12, 2)->default(0)->after('amount');
            }
            if (!Schema::hasColumn('vendor_transactions', 'tax_amount')) {
                $table->decimal('tax_amount', 12, 2)->default(0)->after('coupon_amount');
            }
            if (!Schema::hasColumn('vendor_transactions', 'shipping_amount')) {
                $table->decimal('shipping_amount', 12, 2)->default(0)->after('tax_amount');
            }
            if (!Schema::hasColumn('vendor_transactions', 'commission_rate')) {
                $table->decimal('commission_rate', 5, 2)->nullable()->after('commission');
            }
            if (!Schema::hasColumn('vendor_transactions', 'reference_id')) {
                $table->string('reference_id')->nullable()->after('description');
            }
            if (!Schema::hasColumn('vendor_transactions', 'source')) {
                $table->string('source')->default('order')->after('type');
            }
        });
    }

    public function down()
    {
        Schema::table('vendor_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'coupon_amount', 'tax_amount', 'shipping_amount', 
                'commission_rate', 'reference_id', 'source'
            ]);
        });
    }
}