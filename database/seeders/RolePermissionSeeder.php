<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        // Admin gets all permissions (1-10)
        for ($i = 1; $i <= 10; $i++) {
            DB::table('role_permissions')->insert([
                'role_id' => 1,
                'permission_id' => $i,
                // Remove created_at and updated_at
            ]);
        }

        // User permissions (1,5)
        DB::table('role_permissions')->insert([
            ['role_id' => 2, 'permission_id' => 1],
            ['role_id' => 2, 'permission_id' => 5],
        ]);

        // Vendor permissions (1,2,3,5,6)
        $vendorPermissions = [1, 2, 3, 5, 6];
        foreach ($vendorPermissions as $permId) {
            DB::table('role_permissions')->insert([
                'role_id' => 3,
                'permission_id' => $permId,
            ]);
        }
    }
}