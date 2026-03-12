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
        Schema::create('vendor_transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
    $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();

    $table->decimal('amount',12,2);
    $table->decimal('commission',12,2);
    $table->decimal('net_amount',12,2);

    $table->string('status');
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_transactions');
    }
};
