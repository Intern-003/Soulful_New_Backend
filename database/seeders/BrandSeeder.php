<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Brand;
use Carbon\Carbon;

class BrandSeeder extends Seeder
{
    public function run()
    {
        $brands = [
            [
                'name' => 'Samsung',
                'slug' => 'samsung',
                'logo' => 'brands/samsung.jpg',
                'status' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Apple',
                'slug' => 'apple',
                'logo' => 'brands/apple.jpg',
                'status' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Nike',
                'slug' => 'nike',
                'logo' => 'brands/nike.jpg',
                'status' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Zara',
                'slug' => 'zara',
                'logo' => 'brands/zara.jpg',
                'status' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Sony',
                'slug' => 'sony',
                'logo' => 'brands/sony.jpg',
                'status' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Adidas',
                'slug' => 'adidas',
                'logo' => 'brands/adidas.jpg',
                'status' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        foreach ($brands as $brand) {
            Brand::create($brand);
        }
    }
}