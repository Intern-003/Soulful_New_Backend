<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use Carbon\Carbon;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            [
                'parent_id' => null,
                'name' => 'Electronics',
                'slug' => 'electronics',
                'description' => 'All electronic items',
                'image' => 'categories/electronics.jpg',
                'position' => 1,
                'status' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'parent_id' => 1,
                'name' => 'Mobile Phones',
                'slug' => 'mobile-phones',
                'description' => 'Smartphones and accessories',
                'image' => 'categories/mobiles.jpg',
                'position' => 2,
                'status' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'parent_id' => 1,
                'name' => 'Laptops',
                'slug' => 'laptops',
                'description' => 'Laptops and computers',
                'image' => 'categories/laptops.jpg',
                'position' => 3,
                'status' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'parent_id' => 1,
                'name' => 'Audio',
                'slug' => 'audio',
                'description' => 'Headphones, speakers, etc.',
                'image' => 'categories/audio.jpg',
                'position' => 4,
                'status' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'parent_id' => null,
                'name' => 'Fashion',
                'slug' => 'fashion',
                'description' => 'Clothing and accessories',
                'image' => 'categories/fashion.jpg',
                'position' => 5,
                'status' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'parent_id' => 5,
                'name' => "Men's Clothing",
                'slug' => 'mens-clothing',
                'description' => "Men's wear",
                'image' => 'categories/mens.jpg',
                'position' => 6,
                'status' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'parent_id' => 5,
                'name' => "Women's Clothing",
                'slug' => 'womens-clothing',
                'description' => "Women's wear",
                'image' => 'categories/womens.jpg',
                'position' => 7,
                'status' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'parent_id' => 5,
                'name' => 'Footwear',
                'slug' => 'footwear',
                'description' => 'Shoes and sandals',
                'image' => 'categories/footwear.jpg',
                'position' => 8,
                'status' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}