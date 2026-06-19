<?php
// database/migrations/2024_01_01_000015_create_gst_settings_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGstSettingsTable extends Migration
{
    public function up()
    {
        Schema::create('gst_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('gst_number')->nullable();
            $table->string('pan_number')->nullable();
            $table->boolean('is_gst_registered')->default(false);
            $table->enum('gst_type', ['regular', 'composition', 'casual', 'non_resident'])->nullable();
            $table->decimal('cgst_rate', 5, 2)->default(0);
            $table->decimal('sgst_rate', 5, 2)->default(0);
            $table->decimal('igst_rate', 5, 2)->default(0);
            $table->decimal('cess_rate', 5, 2)->default(0);
            $table->boolean('is_interstate_applicable')->default(true);
            $table->json('hsn_codes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('gst_settings');
    }
}