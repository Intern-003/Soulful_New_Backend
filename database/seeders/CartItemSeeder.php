<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CartItem;
use Carbon\Carbon;

class CartItemSeeder extends Seeder
{
    public function run()
    {
        $items = [
            [
                'cart_id' => 1,
                'product_id' => 2,
                'variant_id' => null,
                'quantity' => 1,
                'price' => 77999.00,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'cart_id' => 1,
                'product_id' => 3,
                'variant_id' => null,
                'quantity' => 1,
                'price' => 27990.00,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'cart_id' => 2,
                'product_id' => 4,
                'variant_id' => 1,
                'quantity' => 1,
                'price' => 5499.00,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'cart_id' => 3,
                'product_id' => 5,
                'variant_id' => 3,
                'quantity' => 2,
                'price' => 2499.00,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'cart_id' => 4,
                'product_id' => 6,
                'variant_id' => null,
                'quantity' => 1,
                'price' => 11999.00,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        foreach ($items as $item) {
            CartItem::create($item);
        }
    }
}