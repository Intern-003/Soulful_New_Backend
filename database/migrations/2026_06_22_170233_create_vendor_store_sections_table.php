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
      Schema::create('vendor_store_sections', function (Blueprint $table) {

    $table->id();

    $table->foreignId('vendor_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->string('title');

    $table->enum('type', [
        'featured_products',
        'new_arrivals',
        'best_sellers',
        'banner',
        'category'
    ]);

    $table->integer('sort_order')->default(0);

    $table->boolean('status')->default(true);

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_store_sections');
    }
};
