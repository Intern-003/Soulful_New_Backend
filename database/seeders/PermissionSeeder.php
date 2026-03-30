<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',

            'orders.view',
            'orders.manage',

            'users.view',
            'users.manage',

            'vendors.manage',
            'settings.manage',
        ];

        foreach ($permissions as $perm) {
            [$module, $action] = explode('.', $perm);

            Permission::updateOrCreate(
                ['name' => $perm],
                [
                    'module' => $module,
                    'action' => $action,
                ]
            );
        }
    }
}