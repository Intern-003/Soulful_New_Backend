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
    Schema::create('otps', function (Blueprint $table) {
        $table->id();
        $table->string('identifier'); // email OR phone
        $table->enum('type', ['email', 'phone']);
        $table->string('otp'); // plain OTP
        $table->timestamp('expires_at');
        $table->timestamps();

        $table->index(['identifier', 'type']); // 🔥 fast lookup
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otps');
    }
};
