<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductVariantAttributeSeeder extends Seeder
{
    public function run()
    {
        $variantAttributes = [
            // Nike Black - Color: Black(5), Size: Medium(2)
            ['variant_id' => 1, 'attribute_id' => 2, 'attribute_value_id' => 5],
            ['variant_id' => 1, 'attribute_id' => 1, 'attribute_value_id' => 2],
            
            // Nike White - Color: White(6), Size: Medium(2)
            ['variant_id' => 2, 'attribute_id' => 2, 'attribute_value_id' => 6],
            ['variant_id' => 2, 'attribute_id' => 1, 'attribute_value_id' => 2],
            
            // Zara Red Small - Color: Red(8), Size: Small(1)
            ['variant_id' => 3, 'attribute_id' => 2, 'attribute_value_id' => 8],
            ['variant_id' => 3, 'attribute_id' => 1, 'attribute_value_id' => 1],
            
            // Zara Red Medium - Color: Red(8), Size: Medium(2)
            ['variant_id' => 4, 'attribute_id' => 2, 'attribute_value_id' => 8],
            ['variant_id' => 4, 'attribute_id' => 1, 'attribute_value_id' => 2],
            
            // Zara Blue Medium - Color: Blue(7), Size: Medium(2)
            ['variant_id' => 5, 'attribute_id' => 2, 'attribute_value_id' => 7],
            ['variant_id' => 5, 'attribute_id' => 1, 'attribute_value_id' => 2],
            
            // Samsung Black 128GB - Color: Black(5), Storage: 128GB(9)
            ['variant_id' => 6, 'attribute_id' => 2, 'attribute_value_id' => 5],
            ['variant_id' => 6, 'attribute_id' => 3, 'attribute_value_id' => 9],
            
            // Samsung White 256GB - Color: White(6), Storage: 256GB(10)
            ['variant_id' => 7, 'attribute_id' => 2, 'attribute_value_id' => 6],
            ['variant_id' => 7, 'attribute_id' => 3, 'attribute_value_id' => 10],
        ];

        foreach ($variantAttributes as $variantAttribute) {
            DB::table('product_variant_attributes')->insert($variantAttribute);
        }
    }
}