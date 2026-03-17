<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProductQuestion;

class ProductQuestionSeeder extends Seeder
{
    public function run(): void
    {
        ProductQuestion::create([
            'product_id' => 1,
            'question' => 'Does this support fast charging?',
        ]);

        ProductQuestion::create([
            'product_id' => 1,
            'question' => 'Is this product waterproof?',
        ]);
    }
}