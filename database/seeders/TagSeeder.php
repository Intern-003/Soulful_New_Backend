<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tag;
use Carbon\Carbon;

class TagSeeder extends Seeder
{
    public function run()
    {
        $tags = [
            ['name' => 'New Arrival', 'slug' => 'new-arrival', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Best Seller', 'slug' => 'best-seller', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Sale', 'slug' => 'sale', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Limited Edition', 'slug' => 'limited-edition', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Eco Friendly', 'slug' => 'eco-friendly', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ];

        foreach ($tags as $tag) {
            Tag::create($tag);
        }
    }
}