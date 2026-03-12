<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use Carbon\Carbon;

class RoleSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            [
                'name' => 'admin',
                'permissions' => json_encode(['all' => true]),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'user',
                'permissions' => json_encode([
                    'view_products' => true, 
                    'place_orders' => true
                ]),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'vendor',
                'permissions' => json_encode([
                    'manage_products' => true, 
                    'view_orders' => true, 
                    'manage_inventory' => true
                ]),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }
}