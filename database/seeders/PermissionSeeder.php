<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use Carbon\Carbon;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
            ['name' => 'view_products', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'create_products', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'edit_products', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'delete_products', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'view_orders', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'manage_orders', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'view_users', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'manage_users', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'manage_vendors', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'manage_settings', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ];

        foreach ($permissions as $permission) {
            Permission::create($permission);
        }
    }
}