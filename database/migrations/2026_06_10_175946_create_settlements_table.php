<?php
// database/migrations/2024_01_01_000012_create_settlements_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSettlementsTable extends Migration
{
    public function up()
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->string('settlement_number')->unique();
            $table->foreignId('vendor_id')->constrained();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total_sales', 15, 2);
            $table->decimal('total_commission', 15, 2);
            $table->decimal('total_tax', 15, 2);
            $table->decimal('total_shipping', 15, 2);
            $table->decimal('total_adjustments', 15, 2)->default(0);
            $table->decimal('settlement_amount', 15, 2);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('payment_method')->nullable();
            $table->string('transaction_id')->nullable();
            $table->text('notes')->nullable();
            $table->json('breakdown')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('settlements');
    }
}