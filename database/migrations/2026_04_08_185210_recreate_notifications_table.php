<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop existing table completely
        Schema::dropIfExists('notifications');

        // Recreate using Laravel standard structure
        Schema::create('notifications', function (Blueprint $table) {

            // UUID primary key
            $table->uuid('id')->primary();

            // Polymorphic relation (required by Laravel)
            $table->string('type');
            $table->morphs('notifiable'); // notifiable_id + notifiable_type

            // Notification payload
            $table->text('data');

            // Read status
            $table->timestamp('read_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};