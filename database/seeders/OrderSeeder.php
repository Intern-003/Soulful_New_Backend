<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order;
use Carbon\Carbon;

class OrderSeeder extends Seeder
{
    public function run()
    {
        $orders = [
            [
                'user_id' => 2,
                'order_number' => 'ORD-2026-0001',
                'address_id' => 1,
                'subtotal' => 105989.00,
                'discount' => 5000.00,
                'tax' => 8000.00,
                'shipping_cost' => 0.00,
                'total' => 108989.00,
                'payment_method' => 'credit_card',
                'payment_status' => 'paid',
                'order_status' => 'delivered',
                'created_at' => Carbon::now()->subDay(),
                'updated_at' => Carbon::now(),
            ],
            [
                'user_id' => 3,
                'order_number' => 'ORD-2026-0002',
                'address_id' => 3,
                'subtotal' => 7998.00,
                'discount' => 500.00,
                'tax' => 600.00,
                'shipping_cost' => 50.00,
                'total' => 8148.00,
                'payment_method' => 'paypal',
                'payment_status' => 'paid',
                'order_status' => 'processing',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'user_id' => 2,
                'order_number' => 'ORD-2026-0003',
                'address_id' => 2,
                'subtotal' => 11999.00,
                'discount' => 1000.00,
                'tax' => 880.00,
                'shipping_cost' => 0.00,
                'total' => 11879.00,
                'payment_method' => 'upi',
                'payment_status' => 'pending',
                'order_status' => 'pending',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        foreach ($orders as $order) {
            Order::create($order);
        }
    }
}