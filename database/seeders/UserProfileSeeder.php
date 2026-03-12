<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UserProfile;
use Carbon\Carbon;

class UserProfileSeeder extends Seeder
{
    public function run()
    {
        $profiles = [
            [
                'user_id' => 1,
                'avatar' => 'profiles/admin.jpg',
                'gender' => 'female',
                'date_of_birth' => '1990-05-15',
                'bio' => 'System Administrator',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'user_id' => 2,
                'avatar' => 'profiles/john.jpg',
                'gender' => 'male',
                'date_of_birth' => '1988-08-22',
                'bio' => 'Tech enthusiast and gadget lover',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'user_id' => 3,
                'avatar' => 'profiles/jane.jpg',
                'gender' => 'female',
                'date_of_birth' => '1992-03-10',
                'bio' => 'Fashion blogger',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'user_id' => 4,
                'avatar' => 'profiles/tech.jpg',
                'gender' => 'male',
                'date_of_birth' => '1985-11-30',
                'bio' => 'Electronics store owner',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'user_id' => 5,
                'avatar' => 'profiles/fashion.jpg',
                'gender' => 'female',
                'date_of_birth' => '1990-07-18',
                'bio' => 'Fashion boutique owner',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        foreach ($profiles as $profile) {
            UserProfile::create($profile);
        }
    }
}