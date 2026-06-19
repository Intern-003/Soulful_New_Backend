<?php
// database/migrations/2024_01_01_000011_update_shipments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateShipmentsTable extends Migration
{
    public function up()
    {
        Schema::table('shipments', function (Blueprint $table) {
            if (!Schema::hasColumn('shipments', 'shipping_mode')) {
                $table->enum('shipping_mode', ['vendor', 'marketplace'])->default('vendor')->after('status');
            }
            if (!Schema::hasColumn('shipments', 'shipping_cost')) {
                $table->decimal('shipping_cost', 10, 2)->default(0)->after('shipping_mode');
            }
            if (!Schema::hasColumn('shipments', 'courier_cost')) {
                $table->decimal('courier_cost', 10, 2)->default(0)->after('shipping_cost');
            }
            if (!Schema::hasColumn('shipments', 'profit')) {
                $table->decimal('profit', 10, 2)->default(0)->after('courier_cost');
            }
            if (!Schema::hasColumn('shipments', 'estimated_delivery')) {
                $table->timestamp('estimated_delivery')->nullable()->after('profit');
            }
        });
    }

    public function down()
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_mode', 'shipping_cost', 'courier_cost', 'profit', 'estimated_delivery'
            ]);
        });
    }
}