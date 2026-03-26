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
        Schema::create('vendors', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('store_name');
    $table->string('store_slug')->unique();
    $table->string('store_logo')->nullable();
    $table->string('store_banner')->nullable();
    $table->text('description')->nullable();
    $table->decimal('commission_rate',5,2)->nullable();
    $table->decimal('rating',3,2)->nullable();
    $table->string('status',20)->default('pending');
    $table->unsignedBigInteger('approved_by')->nullable();
    $table->timestamp('approved_at')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
