<?php
// database/migrations/2024_01_01_000008_create_coupon_usages_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCouponUsagesTable extends Migration
{
    public function up()
    {
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->decimal('discount_amount', 12, 2);
            $table->json('breakdown')->nullable();
            $table->timestamps();
            
            $table->unique(['coupon_id', 'order_id']);
            $table->index(['user_id', 'coupon_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('coupon_usages');
    }
}