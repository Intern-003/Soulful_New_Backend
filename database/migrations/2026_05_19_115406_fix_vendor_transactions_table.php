<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('vendor_transactions', function (Blueprint $table) {
            // Add vendor_wallet_id column
            if (!Schema::hasColumn('vendor_transactions', 'vendor_wallet_id')) {
                $table->unsignedBigInteger('vendor_wallet_id')->nullable()->after('id');
                $table->foreign('vendor_wallet_id')
                    ->references('id')
                    ->on('vendor_wallets')
                    ->onDelete('cascade');
            }
            
            // Add type column (credit/debit)
            if (!Schema::hasColumn('vendor_transactions', 'type')) {
                $table->enum('type', ['credit', 'debit'])->default('credit')->after('net_amount');
            }
            
            // Add description column
            if (!Schema::hasColumn('vendor_transactions', 'description')) {
                $table->string('description')->nullable()->after('type');
            }
            
            // Make vendor_id nullable (since we now use vendor_wallet_id)
            if (Schema::hasColumn('vendor_transactions', 'vendor_id')) {
                $table->unsignedBigInteger('vendor_id')->nullable()->change();
            }
            
            // ========== ADD THIS SECTION ==========
            // Make order_item_id nullable for withdrawal transactions
            if (Schema::hasColumn('vendor_transactions', 'order_item_id')) {
                // Drop the foreign key constraint first
                $table->dropForeign(['order_item_id']);
                
                // Make order_item_id nullable
                $table->unsignedBigInteger('order_item_id')->nullable()->change();
                
                // Re-add the foreign key constraint
                $table->foreign('order_item_id')
                    ->references('id')
                    ->on('order_items')
                    ->onDelete('cascade');
            }
            // =====================================
        });
        
        // Migrate existing data
        $this->migrateExistingData();
    }
    
    private function migrateExistingData()
    {
        // Get all vendor wallets
        $wallets = \App\Models\VendorWallet::all();
        
        foreach ($wallets as $wallet) {
            // Update transactions for vendors
            if ($wallet->vendor_id) {
                \App\Models\VendorTransaction::where('vendor_id', $wallet->vendor_id)
                    ->whereNull('vendor_wallet_id')
                    ->update(['vendor_wallet_id' => $wallet->id]);
            }
            
            // Update transactions for individual sellers
            if ($wallet->user_id) {
                \App\Models\VendorTransaction::where('user_id', $wallet->user_id)
                    ->whereNull('vendor_wallet_id')
                    ->update(['vendor_wallet_id' => $wallet->id]);
            }
        }
    }

    public function down()
    {
        Schema::table('vendor_transactions', function (Blueprint $table) {
            $table->dropForeign(['vendor_wallet_id']);
            $table->dropColumn(['vendor_wallet_id', 'type', 'description']);
            
            // Revert vendor_id back to not nullable
            if (Schema::hasColumn('vendor_transactions', 'vendor_id')) {
                $table->unsignedBigInteger('vendor_id')->nullable(false)->change();
            }
            
            // Revert order_item_id back to not nullable
            if (Schema::hasColumn('vendor_transactions', 'order_item_id')) {
                $table->dropForeign(['order_item_id']);
                $table->unsignedBigInteger('order_item_id')->nullable(false)->change();
                $table->foreign('order_item_id')
                    ->references('id')
                    ->on('order_items')
                    ->onDelete('cascade');
            }
        });
    }
};