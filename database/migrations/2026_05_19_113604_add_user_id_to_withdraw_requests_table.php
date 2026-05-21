<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('withdraw_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('vendor_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Make vendor_id nullable since individual sellers won't have one
            $table->unsignedBigInteger('vendor_id')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('withdraw_requests', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->unsignedBigInteger('vendor_id')->nullable(false)->change();
        });
    }
};