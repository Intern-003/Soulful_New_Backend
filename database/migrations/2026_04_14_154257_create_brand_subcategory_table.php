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
        Schema::create('brand_subcategory', function (Blueprint $table) {
            $table->id();

            // 🔥 BRAND
            $table->foreignId('brand_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // 🔥 SUBCATEGORY (FROM categories TABLE)
            $table->foreignId('subcategory_id')
                  ->constrained('categories')
                  ->cascadeOnDelete();

            // 🔥 OPTIONAL (PREVENT DUPLICATES)
            $table->unique(['brand_id', 'subcategory_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_subcategory');
    }
};
