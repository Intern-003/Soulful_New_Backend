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
    Schema::table('otps', function (Blueprint $table) {

        // temp registration data
        $table->string('name')->nullable()->after('otp');

        $table->string('email')->nullable()->after('name');

        $table->string('phone')->nullable()->after('email');

        $table->string('password')->nullable()->after('phone');

        // security
        $table->integer('attempts')->default(0)->after('password');

        $table->timestamp('blocked_until')
            ->nullable()
            ->after('attempts');
    });
}

public function down(): void
{
    Schema::table('otps', function (Blueprint $table) {

        $table->dropColumn([
            'name',
            'email',
            'phone',
            'password',
            'attempts',
            'blocked_until'
        ]);
    });
}
};