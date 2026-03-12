<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UserSeeder extends Seeder
{
    public function run()
    {
        $users = [
            [
                'name' => 'Riddhi M',
                'email' => 'riddhi@gmail.com',
                'password' => Hash::make('password123'),
                'phone' => '9876543210',
                'role_id' => 1,
                'role' => 'admin',
                'status' => 1,
                'avatar' => 'avatars/admin.jpg',
                'email_verified_at' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'John Customer',
                'email' => 'john@example.com',
                'password' => Hash::make('password123'),
                'phone' => '9876543211',
                'role_id' => 2,
                'role' => 'user',
                'status' => 1,
                'avatar' => 'avatars/john.jpg',
                'email_verified_at' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'password' => Hash::make('password123'),
                'phone' => '9876543212',
                'role_id' => 2,
                'role' => 'user',
                'status' => 1,
                'avatar' => 'avatars/jane.jpg',
                'email_verified_at' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Tech Vendor',
                'email' => 'vendor@example.com',
                'password' => Hash::make('password123'),
                'phone' => '9876543213',
                'role_id' => 3,
                'role' => 'vendor',
                'status' => 1,
                'avatar' => 'avatars/vendor1.jpg',
                'email_verified_at' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Fashion Vendor',
                'email' => 'fashion@example.com',
                'password' => Hash::make('password123'),
                'phone' => '9876543214',
                'role_id' => 3,
                'role' => 'vendor',
                'status' => 1,
                'avatar' => 'avatars/vendor2.jpg',
                'email_verified_at' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        foreach ($users as $user) {
            User::create($user);
        }
    }
}