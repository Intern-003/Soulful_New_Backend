<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id(); // bigint PK
            $table->string('name'); // full name
            $table->string('email')->unique(); // email address
            $table->string('password'); // hashed password
            $table->string('phone')->nullable(); // contact number
            //$table->foreignId('role_id')->constrained('roles')->onDelete('cascade'); // FK to roles
            $table->unsignedBigInteger('role_id'); // match roles.id
$table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->string('avatar')->nullable(); // profile image
            $table->timestamp('email_verified_at')->nullable(); // email verification
            $table->timestamps(); // created_at & updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};