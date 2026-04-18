<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    Schema::create('brand_category', function (Blueprint $table) {
        $table->id();

        $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
$table->foreignId('category_id')
      ->constrained('categories')
      ->cascadeOnDelete();
      
        $table->unique(['brand_id', 'category_id']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_category');
    }
};
