<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AttributeValue;
use Carbon\Carbon;

class AttributeValueSeeder extends Seeder
{
    public function run()
    {
        $values = [
            // Size values
            ['attribute_id' => 1, 'value' => 'Small', 'slug' => 'small', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['attribute_id' => 1, 'value' => 'Medium', 'slug' => 'medium', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['attribute_id' => 1, 'value' => 'Large', 'slug' => 'large', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['attribute_id' => 1, 'value' => 'XL', 'slug' => 'xl', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            
            // Color values
            ['attribute_id' => 2, 'value' => 'Black', 'slug' => 'black', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['attribute_id' => 2, 'value' => 'White', 'slug' => 'white', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['attribute_id' => 2, 'value' => 'Blue', 'slug' => 'blue', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['attribute_id' => 2, 'value' => 'Red', 'slug' => 'red', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            
            // Storage values
            ['attribute_id' => 3, 'value' => '128GB', 'slug' => '128gb', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['attribute_id' => 3, 'value' => '256GB', 'slug' => '256gb', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['attribute_id' => 3, 'value' => '512GB', 'slug' => '512gb', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            
            // RAM values
            ['attribute_id' => 4, 'value' => '8GB', 'slug' => '8gb', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['attribute_id' => 4, 'value' => '12GB', 'slug' => '12gb', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['attribute_id' => 4, 'value' => '16GB', 'slug' => '16gb', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ];

        foreach ($values as $value) {
            AttributeValue::create($value);
        }
    }
}