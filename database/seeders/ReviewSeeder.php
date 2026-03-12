<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Review;
use Carbon\Carbon;

class ReviewSeeder extends Seeder
{
    public function run()
    {
        $reviews = [
            [
                'product_id' => 1,
                'user_id' => 2,
                'rating' => 5,
                'title' => 'Excellent phone',
                'review' => 'Great battery life and camera quality. Very satisfied!',
                'status' => 1,
                'created_at' => Carbon::now()->subDay(),
                'updated_at' => Carbon::now()->subDay(),
            ],
            [
                'product_id' => 3,
                'user_id' => 2,
                'rating' => 5,
                'title' => 'Amazing sound quality',
                'review' => "Best headphones I've ever owned. Noise cancellation is top-notch.",
                'status' => 1,
                'created_at' => Carbon::now()->subDay(),
                'updated_at' => Carbon::now()->subDay(),
            ],
            [
                'product_id' => 4,
                'user_id' => 3,
                'rating' => 4,
                'title' => 'Very comfortable',
                'review' => 'Good for running and daily use. Sizing is accurate.',
                'status' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'product_id' => 5,
                'user_id' => 3,
                'rating' => 5,
                'title' => 'Beautiful dress',
                'review' => 'Perfect fit and fabric quality. Received many compliments!',
                'status' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        foreach ($reviews as $review) {
            Review::create($review);
        }
    }
}