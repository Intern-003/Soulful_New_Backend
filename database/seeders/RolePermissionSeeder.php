<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        $admin = Role::where('name', 'admin')->first();
        $user = Role::where('name', 'user')->first();
        $vendor = Role::where('name', 'vendor')->first();

        // Get all permissions
        $allPermissions = Permission::pluck('id');

        // Admin → all permissions
        $admin->permissions()->sync($allPermissions);

        // User → limited permissions
        $userPermissions = Permission::whereIn('name', [
            'view_products',
            'place_orders'
        ])->pluck('id');

        $user->permissions()->sync($userPermissions);

        // Vendor → specific permissions
        $vendorPermissions = Permission::whereIn('name', [
            'manage_products',
            'view_orders',
            'manage_inventory'
        ])->pluck('id');

        $vendor->permissions()->sync($vendorPermissions);
    }
}