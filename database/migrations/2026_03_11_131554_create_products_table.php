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
       Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
    $table->foreignId('category_id')->constrained()->cascadeOnDelete();
    $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();

    $table->string('name');
    $table->string('slug')->unique();

    $table->text('short_description')->nullable();
    $table->longText('description')->nullable();

    $table->string('sku')->nullable();

    $table->decimal('price',10,2);
    $table->decimal('discount_price',10,2)->nullable();
    $table->decimal('cost_price',10,2)->nullable();

    $table->integer('stock')->nullable();

    $table->decimal('weight',8,2)->nullable();
    $table->decimal('length',8,2)->nullable();
    $table->decimal('width',8,2)->nullable();
    $table->decimal('height',8,2)->nullable();

    $table->boolean('status')->default(1);
    $table->boolean('is_featured')->default(false);
    $table->boolean('is_approved')->default(false);

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
        Schema::dropIfExists('products');
    }
};
