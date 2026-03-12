<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Vendor;
use Carbon\Carbon;

class VendorSeeder extends Seeder
{
    public function run()
    {
        $vendors = [
            [
                'user_id' => 4,
                'store_name' => 'Tech Galaxy Store',
                'store_slug' => 'tech-galaxy',
                'store_logo' => 'logos/techgalaxy.jpg',
                'store_banner' => 'banners/techgalaxy.jpg',
                'description' => 'Best electronics store with latest gadgets',
                'commission_rate' => 10.00,
                'rating' => 4.50,
                'status' => 'approved',
                'approved_by' => 1,
                'approved_at' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'user_id' => 5,
                'store_name' => 'Fashion Hub',
                'store_slug' => 'fashion-hub',
                'store_logo' => 'logos/fashionhub.jpg',
                'store_banner' => 'banners/fashionhub.jpg',
                'description' => 'Trendy clothing store for all ages',
                'commission_rate' => 12.00,
                'rating' => 4.20,
                'status' => 'approved',
                'approved_by' => 1,
                'approved_at' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        foreach ($vendors as $vendor) {
            Vendor::create($vendor);
        }
    }
}