<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        $adminRole = Role::where('name', 'admin')->first();
        $userRole = Role::where('name', 'user')->first();
        $vendorRole = Role::where('name', 'vendor')->first();

        $users = [
            [
                'name' => 'Super Admin',
                'email' => 'admin@example.com',
                'phone' => '9876543210',
                'role_id' => $adminRole->id,
                'status' => true,
            ],
            [
                'name' => 'John Customer',
                'email' => 'john@example.com',
                'phone' => '9876543211',
                'role_id' => $userRole->id,
                'status' => true,
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'phone' => '9876543212',
                'role_id' => $userRole->id,
                'status' => true,
            ],
            [
                'name' => 'Tech Vendor',
                'email' => 'vendor@example.com',
                'phone' => '9876543213',
                'role_id' => $vendorRole->id,
                'status' => true,
            ],
            [
                'name' => 'Fashion Vendor',
                'email' => 'fashion@example.com',
                'phone' => '9876543214',
                'role_id' => $vendorRole->id,
                'status' => true,
            ],
            [
                'name' => 'Electronics Vendor',
                'email' => 'electronics@example.com',
                'phone' => '9876543215',
                'role_id' => $vendorRole->id,
                'status' => true,
            ],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'role_id' => $data['role_id'],
                    'password' => Hash::make('password123'),
                    'status' => $data['status'],
                    'email_verified_at' => now(),
                ]
            );
        }
        
        $this->command->info('Users seeded successfully!');
        $this->command->info('Default password for all users: password123');
    }
}