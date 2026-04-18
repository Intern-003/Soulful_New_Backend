<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $variants = DB::table('product_variants')
            ->whereNotNull('image')
            ->get();

        foreach ($variants as $variant) {
            DB::table('product_images')->insert([
                'product_id' => $variant->product_id,
                'variant_id' => $variant->id,
                'image_url' => $variant->image,
                'is_primary' => 1,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $table->dropColumn('image');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('image')->nullable();
        });
    }

};
