<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateProductsAddApprovalStatusFields extends Migration
{
public function up()
{
    Schema::table('products', function (Blueprint $table) {
        $table->enum('approval_status', ['pending', 'approved', 'rejected'])
              ->default('pending')
              ->after('is_featured');

        $table->decimal('commission', 5, 2)
              ->nullable()
              ->after('approval_status');

        $table->text('rejection_reason')
              ->nullable()
              ->after('commission');
    });

    // ✅ SAFER mapping
    DB::statement("
        UPDATE products 
        SET approval_status = 
            CASE 
                WHEN is_approved = 1 THEN 'approved'
                ELSE 'pending'
            END
    ");

    Schema::table('products', function (Blueprint $table) {
        $table->dropColumn('is_approved');
    });
}

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {

            // ✅ Restore old column
            $table->boolean('is_approved')->default(0);

            // ❌ Remove new fields
            $table->dropColumn([
                'approval_status',
                'commission',
                'rejection_reason'
            ]);
        });
    }

};
