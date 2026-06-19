<?php
// database/migrations/2024_01_01_000010_update_vendor_wallets_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateVendorWalletsTable extends Migration
{
    public function up()
    {
        Schema::table('vendor_wallets', function (Blueprint $table) {
            if (!Schema::hasColumn('vendor_wallets', 'pending_balance')) {
                $table->decimal('pending_balance', 15, 2)->default(0)->after('balance');
            }
            if (!Schema::hasColumn('vendor_wallets', 'available_balance')) {
                $table->decimal('available_balance', 15, 2)->default(0)->after('pending_balance');
            }
            if (!Schema::hasColumn('vendor_wallets', 'total_earned')) {
                $table->decimal('total_earned', 15, 2)->default(0)->after('available_balance');
            }
            if (!Schema::hasColumn('vendor_wallets', 'total_withdrawn')) {
                $table->decimal('total_withdrawn', 15, 2)->default(0)->after('total_earned');
            }
            if (!Schema::hasColumn('vendor_wallets', 'total_commission_paid')) {
                $table->decimal('total_commission_paid', 15, 2)->default(0)->after('total_withdrawn');
            }
        });
    }

    public function down()
    {
        Schema::table('vendor_wallets', function (Blueprint $table) {
            $table->dropColumn([
                'pending_balance', 'available_balance', 'total_earned',
                'total_withdrawn', 'total_commission_paid'
            ]);
        });
    }
}