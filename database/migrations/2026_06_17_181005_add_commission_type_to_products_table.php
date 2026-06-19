<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    // In the migration file
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('commission_type', ['percentage', 'fixed'])->default('percentage')->after('commission');
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('commission_type');
        });
    }
};
