<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\OrderItem;
use Carbon\Carbon;

class OrderItemSeeder extends Seeder
{
    public function run()
    {
        $items = [
            // Order 1 items
            [
                'order_id' => 1,
                'product_id' => 2,
                'variant_id' => null,
                'vendor_id' => 1,
                'price' => 77999.00,
                'quantity' => 1,
                'total' => 77999.00,
                'status' => 'delivered',
                'created_at' => Carbon::now()->subDay(),
                'updated_at' => Carbon::now(),
            ],
            [
                'order_id' => 1,
                'product_id' => 3,
                'variant_id' => null,
                'vendor_id' => 1,
                'price' => 27990.00,
                'quantity' => 1,
                'total' => 27990.00,
                'status' => 'delivered',
                'created_at' => Carbon::now()->subDay(),
                'updated_at' => Carbon::now(),
            ],
            
            // Order 2 items
            [
                'order_id' => 2,
                'product_id' => 4,
                'variant_id' => 1,
                'vendor_id' => 2,
                'price' => 5499.00,
                'quantity' => 1,
                'total' => 5499.00,
                'status' => 'processing',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'order_id' => 2,
                'product_id' => 5,
                'variant_id' => 3,
                'vendor_id' => 2,
                'price' => 2499.00,
                'quantity' => 1,
                'total' => 2499.00,
                'status' => 'processing',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            
            // Order 3 items
            [
                'order_id' => 3,
                'product_id' => 6,
                'variant_id' => null,
                'vendor_id' => 2,
                'price' => 11999.00,
                'quantity' => 1,
                'total' => 11999.00,
                'status' => 'pending',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        foreach ($items as $item) {
            OrderItem::create($item);
        }
    }
}