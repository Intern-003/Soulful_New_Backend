<?php
// database/seeders/RolePermissionSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Disable foreign key checks
        Schema::disableForeignKeyConstraints();
        DB::table('role_permissions')->truncate();
        DB::table('permissions')->truncate();
        DB::table('roles')->truncate();
        Schema::enableForeignKeyConstraints();

        // ==================== CREATE ROLES ====================
        $adminRole = Role::create(['name' => 'admin']);
        $vendorRole = Role::create(['name' => 'vendor']);
        
        // Customer role - NO PERMISSIONS assigned
        $customerRole = Role::create(['name' => 'customer']);

        // ==================== DEFINE ALL PERMISSIONS ====================
        $permissions = [

            // ==================== VENDOR & ADMIN SHARED PERMISSIONS ====================
            ['module' => 'profile', 'action' => 'view', 'name' => 'profile.view'],
            ['module' => 'profile', 'action' => 'update', 'name' => 'profile.update'],
            ['module' => 'address', 'action' => 'view', 'name' => 'address.view'],
            ['module' => 'address', 'action' => 'create', 'name' => 'address.create'],
            ['module' => 'address', 'action' => 'update', 'name' => 'address.update'],
            ['module' => 'address', 'action' => 'delete', 'name' => 'address.delete'],
            
            // ==================== VENDOR SPECIFIC PERMISSIONS ====================
            ['module' => 'vendor.dashboard', 'action' => 'view', 'name' => 'vendor.dashboard.view'],
            ['module' => 'vendor.documents', 'action' => 'view', 'name' => 'vendor.documents.view'],
            ['module' => 'vendor.documents', 'action' => 'create', 'name' => 'vendor.documents.create'],
            ['module' => 'vendor.documents', 'action' => 'delete', 'name' => 'vendor.documents.delete'],
            ['module' => 'vendor.inventory', 'action' => 'view', 'name' => 'vendor.inventory.view'],
            
            // ==================== PRODUCT PERMISSIONS (Vendor) ====================
            ['module' => 'product', 'action' => 'view', 'name' => 'product.view'],
            ['module' => 'product', 'action' => 'create', 'name' => 'product.create'],
            ['module' => 'product', 'action' => 'update', 'name' => 'product.update'],
            ['module' => 'product', 'action' => 'delete', 'name' => 'product.delete'],
            ['module' => 'product', 'action' => 'show', 'name' => 'product.show'],
            
            // ==================== VARIANT PERMISSIONS (Vendor) ====================
            ['module' => 'variant', 'action' => 'view', 'name' => 'variant.view'],
            ['module' => 'variant', 'action' => 'create', 'name' => 'variant.create'],
            ['module' => 'variant', 'action' => 'update', 'name' => 'variant.update'],
            ['module' => 'variant', 'action' => 'delete', 'name' => 'variant.delete'],
            
            // ==================== COUPON PERMISSIONS (Vendor) ====================
            ['module' => 'coupon', 'action' => 'view', 'name' => 'coupon.view'],
            ['module' => 'coupon', 'action' => 'create', 'name' => 'coupon.create'],
            ['module' => 'coupon', 'action' => 'update', 'name' => 'coupon.update'],
            ['module' => 'coupon', 'action' => 'delete', 'name' => 'coupon.delete'],
            
            // ==================== ORDER PERMISSIONS (Vendor) ====================
            ['module' => 'order', 'action' => 'view', 'name' => 'order.view'],
            ['module' => 'order', 'action' => 'update', 'name' => 'order.update'],
            ['module' => 'order', 'action' => 'shipment', 'name' => 'order.shipment'],
            
            // ==================== WALLET PERMISSIONS (Vendor) ====================
            ['module' => 'wallet', 'action' => 'view', 'name' => 'wallet.view'],
            ['module' => 'wallet', 'action' => 'withdraw', 'name' => 'wallet.withdraw'],
            
            // ==================== QUESTION PERMISSIONS (Vendor) ====================
            ['module' => 'question', 'action' => 'answer', 'name' => 'question.answer'],
            
            // ==================== ADMIN ONLY PERMISSIONS ====================
            // Auth
            ['module' => 'auth', 'action' => 'logout', 'name' => 'auth.logout'],
            ['module' => 'auth', 'action' => 'refresh', 'name' => 'auth.refresh'],
            
            // Product Approval
            ['module' => 'product', 'action' => 'approve', 'name' => 'product.approve'],
            
            // Category Management
            ['module' => 'category', 'action' => 'view', 'name' => 'category.view'],
            ['module' => 'category', 'action' => 'create', 'name' => 'category.create'],
            ['module' => 'category', 'action' => 'update', 'name' => 'category.update'],
            ['module' => 'category', 'action' => 'delete', 'name' => 'category.delete'],
            
            // Attribute Management
            ['module' => 'attribute', 'action' => 'view', 'name' => 'attribute.view'],
            ['module' => 'attribute', 'action' => 'create', 'name' => 'attribute.create'],
            ['module' => 'attribute', 'action' => 'update', 'name' => 'attribute.update'],
            ['module' => 'attribute', 'action' => 'delete', 'name' => 'attribute.delete'],
            ['module' => 'attributevalues', 'action' => 'view', 'name' => 'attributevalues.view'],
            
            // Brand Management
            ['module' => 'brand', 'action' => 'view', 'name' => 'brand.view'],
            ['module' => 'brand', 'action' => 'create', 'name' => 'brand.create'],
            ['module' => 'brand', 'action' => 'update', 'name' => 'brand.update'],
            ['module' => 'brand', 'action' => 'delete', 'name' => 'brand.delete'],
            
            // Commission Management
            ['module' => 'commission', 'action' => 'view', 'name' => 'commission.view'],
            ['module' => 'commission', 'action' => 'update', 'name' => 'commission.update'],
            ['module' => 'commission', 'action' => 'settle', 'name' => 'commission.settle'],
            
            // Withdrawal Management
            ['module' => 'withdrawal', 'action' => 'view', 'name' => 'withdrawal.view'],
            ['module' => 'withdrawal', 'action' => 'approve', 'name' => 'withdrawal.approve'],
            
            // Banner Management
            ['module' => 'banner', 'action' => 'view', 'name' => 'banner.view'],
            ['module' => 'banner', 'action' => 'create', 'name' => 'banner.create'],
            ['module' => 'banner', 'action' => 'update', 'name' => 'banner.update'],
            ['module' => 'banner', 'action' => 'delete', 'name' => 'banner.delete'],
            
            // Dashboard
            ['module' => 'dashboard', 'action' => 'view', 'name' => 'dashboard.view'],
            
            // Analytics
            ['module' => 'analytics', 'action' => 'view', 'name' => 'analytics.view'],
            
            // Reports
            ['module' => 'report', 'action' => 'view', 'name' => 'report.view'],
            
            // Support Management (Admin)
            ['module' => 'support', 'action' => 'view', 'name' => 'support.view'],
            ['module' => 'support', 'action' => 'reply', 'name' => 'support.reply'],
            ['module' => 'support', 'action' => 'update', 'name' => 'support.update'],
            
            // Role Management
            ['module' => 'role', 'action' => 'view', 'name' => 'role.view'],
            ['module' => 'role', 'action' => 'create', 'name' => 'role.create'],
            ['module' => 'role', 'action' => 'update', 'name' => 'role.update'],
            ['module' => 'role', 'action' => 'delete', 'name' => 'role.delete'],
            
            // Permission Management
            ['module' => 'permission', 'action' => 'view', 'name' => 'permission.view'],
            ['module' => 'permission', 'action' => 'create', 'name' => 'permission.create'],
            ['module' => 'permission', 'action' => 'update', 'name' => 'permission.update'],
            ['module' => 'permission', 'action' => 'delete', 'name' => 'permission.delete'],
            
            // User Management
            ['module' => 'user', 'action' => 'view', 'name' => 'user.view'],
            ['module' => 'user', 'action' => 'assign-role', 'name' => 'user.assign-role'],
            ['module' => 'profiles', 'action' => 'view', 'name' => 'profiles.view'],
            
            // Vendor Management (Admin)
            ['module' => 'vendor', 'action' => 'view', 'name' => 'vendor.view'],
            ['module' => 'vendor', 'action' => 'approve', 'name' => 'vendor.approve'],
            ['module' => 'vendor', 'action' => 'update', 'name' => 'vendor.update'],
            
            // Settings
            ['module' => 'settings', 'action' => 'view', 'name' => 'settings.view'],
            ['module' => 'settings', 'action' => 'update', 'name' => 'settings.update'],
            
            // Logs
            ['module' => 'log', 'action' => 'view', 'name' => 'log.view'],
            
            // Admin Coupon Management
            ['module' => 'coupon', 'action' => 'admin_view', 'name' => 'coupon.admin_view'],
        ];

        // Insert all permissions
        foreach ($permissions as $perm) {
            Permission::create($perm);
        }

        // ==================== ASSIGN PERMISSIONS TO ROLES ====================

        // ✅ ADMIN: ALL PERMISSIONS
        $allPermissions = Permission::all();
        foreach ($allPermissions as $permission) {
            DB::table('role_permissions')->insert([
                'role_id' => $adminRole->id,
                'permission_id' => $permission->id,
            ]);
        }

        // ✅ VENDOR: Specific permissions only
        $vendorPermissions = [
            // Profile & Address
            'profile.view', 'profile.update',
            'address.view', 'address.create', 'address.update', 'address.delete',
            
            // Dashboard
            'vendor.dashboard.view',
            
            // Documents
            'vendor.documents.view', 'vendor.documents.create', 'vendor.documents.delete',
            
            // Inventory
            'vendor.inventory.view',
            
            // Products
            'product.view', 'product.create', 'product.update', 'product.delete', 'product.show',
            
            // Variants
            'variant.view', 'variant.create', 'variant.update', 'variant.delete',
            
            // Coupons
            'coupon.view', 'coupon.create', 'coupon.update', 'coupon.delete',
            
            // Orders
            'order.view', 'order.update', 'order.shipment',
            
            // Wallet
            'wallet.view', 'wallet.withdraw',
            
            // Questions
            'question.answer',
        ];
        
        foreach ($vendorPermissions as $permName) {
            $permission = Permission::where('name', $permName)->first();
            if ($permission) {
                DB::table('role_permissions')->insert([
                    'role_id' => $vendorRole->id,
                    'permission_id' => $permission->id,
                ]);
            }
        }

        // ✅ CUSTOMER: NO PERMISSIONS - Just authentication is enough

        // ==================== CREATE DEFAULT ADMIN USER ====================
        $adminUser = \App\Models\User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'role_id' => $adminRole->id,
            ]
        );

        // ==================== CREATE DEFAULT VENDOR USER ====================
        $vendorUser = \App\Models\User::updateOrCreate(
            ['email' => 'vendor@example.com'],
            [
                'name' => 'Test Vendor',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'role_id' => $vendorRole->id,
            ]
        );

        // ✅ FIXED: Create vendor record with correct column names based on your table
        \App\Models\Vendor::updateOrCreate(
            ['user_id' => $vendorUser->id],
            [
                'user_id' => $vendorUser->id,
                'store_name' => 'Test Store',
                'store_slug' => 'test-store',
                'commission_rate' => 10,
                'status' => 'active',
                // 'contact_email' is NOT in your table - removed
                // 'commission_type' is NOT in your table - removed
            ]
        );

        // ==================== CREATE DEFAULT CUSTOMER USER ====================
        \App\Models\User::updateOrCreate(
            ['email' => 'customer@example.com'],
            [
                'name' => 'Test Customer',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'role_id' => $customerRole->id,
            ]
        );

        $this->command->info('========================================');
        $this->command->info('Roles and permissions seeded successfully!');
        $this->command->info('========================================');
        $this->command->info('Admin Email: admin@example.com | Password: password');
        $this->command->info('Vendor Email: vendor@example.com | Password: password');
        $this->command->info('Customer Email: customer@example.com | Password: password');
        $this->command->info('========================================');
        $this->command->info('Total Permissions: ' . Permission::count());
        $this->command->info('Admin Permissions: ' . DB::table('role_permissions')->where('role_id', $adminRole->id)->count());
        $this->command->info('Vendor Permissions: ' . DB::table('role_permissions')->where('role_id', $vendorRole->id)->count());
        $this->command->info('Customer Permissions: 0 (No permissions needed)');
        $this->command->info('========================================');
    }
}