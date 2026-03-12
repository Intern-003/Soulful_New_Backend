<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProductVariant;
use Carbon\Carbon;

class ProductVariantSeeder extends Seeder
{
    public function run()
    {
        $variants = [
            // Nike Air Max variants
            [
                'product_id' => 4,
                'sku' => 'NAM-BLK-42',
                'barcode' => '890123456789',
                'price' => 5999.00,
                'discount_price' => 5499.00,
                'stock' => 25,
                'weight' => 0.30,
                'image' => 'variants/nike_black.jpg',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'product_id' => 4,
                'sku' => 'NAM-WHT-42',
                'barcode' => '890123456790',
                'price' => 5999.00,
                'discount_price' => 5499.00,
                'stock' => 25,
                'weight' => 0.30,
                'image' => 'variants/nike_white.jpg',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            
            // Zara Summer Dress variants
            [
                'product_id' => 5,
                'sku' => 'ZSD-RED-S',
                'barcode' => '890123456791',
                'price' => 2999.00,
                'discount_price' => 2499.00,
                'stock' => 25,
                'weight' => 0.20,
                'image' => 'variants/zara_red_s.jpg',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'product_id' => 5,
                'sku' => 'ZSD-RED-M',
                'barcode' => '890123456792',
                'price' => 2999.00,
                'discount_price' => 2499.00,
                'stock' => 25,
                'weight' => 0.20,
                'image' => 'variants/zara_red_m.jpg',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'product_id' => 5,
                'sku' => 'ZSD-BLU-M',
                'barcode' => '890123456793',
                'price' => 2999.00,
                'discount_price' => 2499.00,
                'stock' => 25,
                'weight' => 0.20,
                'image' => 'variants/zara_blue_m.jpg',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            
            // Samsung S23 variants
            [
                'product_id' => 1,
                'sku' => 'SAMS23-BLK-128',
                'barcode' => '890123456794',
                'price' => 79999.00,
                'discount_price' => 74999.00,
                'stock' => 15,
                'weight' => 0.17,
                'image' => 'variants/s23_black.jpg',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'product_id' => 1,
                'sku' => 'SAMS23-WHT-256',
                'barcode' => '890123456795',
                'price' => 84999.00,
                'discount_price' => 79999.00,
                'stock' => 10,
                'weight' => 0.17,
                'image' => 'variants/s23_white.jpg',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        foreach ($variants as $variant) {
            ProductVariant::create($variant);
        }
    }
}