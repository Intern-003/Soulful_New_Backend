<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Drop foreign key constraints from all tables that have vendor_id
        $tables = ['order_items', 'vendor_wallets', 'vendor_transactions'];
        
        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'vendor_id')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    try {
                        // Try to drop foreign key with standard name
                        $t->dropForeign(['vendor_id']);
                    } catch (\Exception $e) {
                        try {
                            // Try alternative foreign key name
                            $t->dropForeign($table . '_vendor_id_foreign');
                        } catch (\Exception $e2) {
                            // Try another common naming convention
                            try {
                                $t->dropForeign('fk_' . $table . '_vendor_id');
                            } catch (\Exception $e3) {
                                // Foreign key might not exist or has different name
                            }
                        }
                    }
                });
            }
        }

        // Step 2: Now change vendor_id to string in all tables
        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'vendor_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->string('vendor_id')->nullable()->change();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['order_items', 'vendor_wallets', 'vendor_transactions'];
        
        // Change back to integer
        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'vendor_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('vendor_id')->nullable()->change();
                });
            }
        }
    }
};