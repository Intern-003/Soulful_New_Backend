<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Role as string (admin/user/vendor)
            $table->string('role', 20)->default('user')->after('role_id');

            // Account status (0 = inactive, 1 = active)
            $table->tinyInteger('status')->default(1)->after('role');

            // Remember token for auth
            $table->string('remember_token', 100)->nullable()->after('status');

            // Last login timestamp
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'status', 'remember_token', 'last_login_at']);
        });
    }
};