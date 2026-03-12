<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Attribute;
use Carbon\Carbon;

class AttributeSeeder extends Seeder
{
    public function run()
    {
        $attributes = [
            ['name' => 'Size', 'slug' => 'size', 'status' => 1, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Color', 'slug' => 'color', 'status' => 1, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Storage', 'slug' => 'storage', 'status' => 1, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'RAM', 'slug' => 'ram', 'status' => 1, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ];

        foreach ($attributes as $attribute) {
            Attribute::create($attribute);
        }
    }
}