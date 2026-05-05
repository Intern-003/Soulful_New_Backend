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
    Schema::table('banners', function (Blueprint $table) {
        $table->string('button_text')->nullable()->after('subtitle');
        $table->string('button_link')->nullable()->after('button_text');
    });
}

public function down()
{
    Schema::table('banners', function (Blueprint $table) {
        $table->dropColumn(['button_text', 'button_link']);
    });
}
};
