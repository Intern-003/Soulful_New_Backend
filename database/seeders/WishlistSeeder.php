<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class WishlistSeeder extends Seeder
{
    public function run()
    {
        $wishlists = [
            [
                'user_id' => 2,
                'product_id' => 2,
                'created_at' => Carbon::now(),
                // 'updated_at' => Carbon::now(),  // REMOVE THIS LINE
            ],
            [
                'user_id' => 2,
                'product_id' => 4,
                'created_at' => Carbon::now(),
                // 'updated_at' => Carbon::now(),  // REMOVE THIS LINE
            ],
            [
                'user_id' => 3,
                'product_id' => 1,
                'created_at' => Carbon::now(),
                // 'updated_at' => Carbon::now(),  // REMOVE THIS LINE
            ],
            [
                'user_id' => 3,
                'product_id' => 5,
                'created_at' => Carbon::now(),
                // 'updated_at' => Carbon::now(),  // REMOVE THIS LINE
            ],
        ];

        DB::table('wishlists')->insert($wishlists);
    }
}