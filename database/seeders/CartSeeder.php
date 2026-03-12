<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Cart;
use Carbon\Carbon;

class CartSeeder extends Seeder
{
    public function run()
    {
        $carts = [
            [
                'user_id' => 2,
                'guest_token' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'user_id' => 3,
                'guest_token' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'user_id' => null,
                'guest_token' => 'guest_abc123',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'user_id' => null,
                'guest_token' => 'guest_xyz789',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        foreach ($carts as $cart) {
            Cart::create($cart);
        }
    }
}