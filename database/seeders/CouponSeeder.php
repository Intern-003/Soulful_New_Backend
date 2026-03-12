<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Coupon;
use Carbon\Carbon;

class CouponSeeder extends Seeder
{
    public function run()
    {
        $coupons = [
            [
                'vendor_id' => null,
                'code' => 'WELCOME10',
                'type' => 'percentage',
                'value' => 10.00,
                'min_order_amount' => 500.00,
                'max_discount' => 500.00,
                'usage_limit' => 1000,
                'used_count' => 125,
                'start_date' => Carbon::now()->subDays(10),
                'expiry_date' => Carbon::now()->addDays(20),
                'status' => 1,
                'created_at' => Carbon::now()->subDays(10),
                'updated_at' => Carbon::now()->subDays(10),
            ],
            [
                'vendor_id' => null,
                'code' => 'SAVE500',
                'type' => 'fixed',
                'value' => 500.00,
                'min_order_amount' => 2000.00,
                'max_discount' => null,
                'usage_limit' => 500,
                'used_count' => 42,
                'start_date' => Carbon::now()->subDays(10),
                'expiry_date' => Carbon::now()->addDays(50),
                'status' => 1,
                'created_at' => Carbon::now()->subDays(10),
                'updated_at' => Carbon::now()->subDays(10),
            ],
            [
                'vendor_id' => 1,
                'code' => 'SAMSUNG50',
                'type' => 'fixed',
                'value' => 500.00,
                'min_order_amount' => 50000.00,
                'max_discount' => null,
                'usage_limit' => 50,
                'used_count' => 5,
                'start_date' => Carbon::now()->subDays(2),
                'expiry_date' => Carbon::now()->addDays(20),
                'status' => 1,
                'created_at' => Carbon::now()->subDays(2),
                'updated_at' => Carbon::now()->subDays(2),
            ],
            [
                'vendor_id' => 2,
                'code' => 'FASHION20',
                'type' => 'percentage',
                'value' => 20.00,
                'min_order_amount' => 1000.00,
                'max_discount' => 1000.00,
                'usage_limit' => 200,
                'used_count' => 18,
                'start_date' => Carbon::now()->subDays(1),
                'expiry_date' => Carbon::now()->addDays(30),
                'status' => 1,
                'created_at' => Carbon::now()->subDays(1),
                'updated_at' => Carbon::now()->subDays(1),
            ],
        ];

        foreach ($coupons as $coupon) {
            Coupon::create($coupon);
        }
    }
}