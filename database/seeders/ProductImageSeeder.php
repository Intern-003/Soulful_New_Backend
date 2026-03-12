<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProductImage;
use Carbon\Carbon;

class ProductImageSeeder extends Seeder
{
    public function run()
    {
        $images = [
            // Product 1 images
            ['product_id' => 1, 'image_url' => 'products/s23_1.jpg', 'is_primary' => 1, 'sort_order' => 1, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['product_id' => 1, 'image_url' => 'products/s23_2.jpg', 'is_primary' => 0, 'sort_order' => 2, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['product_id' => 1, 'image_url' => 'products/s23_3.jpg', 'is_primary' => 0, 'sort_order' => 3, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            
            // Product 2 images
            ['product_id' => 2, 'image_url' => 'products/iphone14_1.jpg', 'is_primary' => 1, 'sort_order' => 1, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['product_id' => 2, 'image_url' => 'products/iphone14_2.jpg', 'is_primary' => 0, 'sort_order' => 2, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            
            // Product 3 images
            ['product_id' => 3, 'image_url' => 'products/sony_1.jpg', 'is_primary' => 1, 'sort_order' => 1, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['product_id' => 3, 'image_url' => 'products/sony_2.jpg', 'is_primary' => 0, 'sort_order' => 2, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            
            // Product 4 images
            ['product_id' => 4, 'image_url' => 'products/nike_1.jpg', 'is_primary' => 1, 'sort_order' => 1, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['product_id' => 4, 'image_url' => 'products/nike_2.jpg', 'is_primary' => 0, 'sort_order' => 2, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            
            // Product 5 images
            ['product_id' => 5, 'image_url' => 'products/zara_1.jpg', 'is_primary' => 1, 'sort_order' => 1, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['product_id' => 5, 'image_url' => 'products/zara_2.jpg', 'is_primary' => 0, 'sort_order' => 2, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            
            // Product 6 images
            ['product_id' => 6, 'image_url' => 'products/adidas_1.jpg', 'is_primary' => 1, 'sort_order' => 1, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ];

        foreach ($images as $image) {
            ProductImage::create($image);
        }
    }
}