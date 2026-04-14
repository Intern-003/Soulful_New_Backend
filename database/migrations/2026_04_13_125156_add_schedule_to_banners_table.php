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
            $table->timestamp('start_date')->nullable()->after('description');
            $table->timestamp('end_date')->nullable()->after('start_date');
        });
    }

    public function down()
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date']);
        });
    }
};
