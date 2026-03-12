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
        Schema::create('coupons', function (Blueprint $table) {
    $table->id();
    $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();

    $table->string('code')->unique();
    $table->string('type');
    $table->decimal('value',10,2);

    $table->decimal('min_order_amount',10,2)->nullable();
    $table->decimal('max_discount',10,2)->nullable();

    $table->integer('usage_limit')->nullable();
    $table->integer('used_count')->default(0);

    $table->dateTime('start_date')->nullable();
    $table->dateTime('expiry_date')->nullable();

    $table->boolean('status')->default(1);

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
