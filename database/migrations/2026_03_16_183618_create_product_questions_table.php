<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_questions', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // who asked

            // Question & Answer
            $table->text('question');
            $table->text('answer')->nullable();

            // Vendor who answered
            $table->foreignId('answered_by')->nullable()->constrained('vendors')->nullOnDelete();

            $table->timestamp('answered_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_questions');
    }
};