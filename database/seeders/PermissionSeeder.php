<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
            // Auth & Profile Permissions
            ['name' => 'auth.logout', 'module' => 'auth', 'action' => 'logout'],
            ['name' => 'auth.refresh', 'module' => 'auth', 'action' => 'refresh'],
            ['name' => 'auth.verify', 'module' => 'auth', 'action' => 'verify'],
            ['name' => 'profile.view', 'module' => 'profile', 'action' => 'view'],
            ['name' => 'profile.update', 'module' => 'profile', 'action' => 'update'],
            
            // Address Permissions
            ['name' => 'address.view', 'module' => 'address', 'action' => 'view'],
            ['name' => 'address.create', 'module' => 'address', 'action' => 'create'],
            ['name' => 'address.update', 'module' => 'address', 'action' => 'update'],
            ['name' => 'address.delete', 'module' => 'address', 'action' => 'delete'],
            
            // Cart Permissions
            ['name' => 'cart.view', 'module' => 'cart', 'action' => 'view'],
            ['name' => 'cart.create', 'module' => 'cart', 'action' => 'create'],
            ['name' => 'cart.update', 'module' => 'cart', 'action' => 'update'],
            ['name' => 'cart.delete', 'module' => 'cart', 'action' => 'delete'],
            
            // Category Permissions
            ['name' => 'category.view', 'module' => 'category', 'action' => 'view'],
            ['name' => 'category.create', 'module' => 'category', 'action' => 'create'],
            ['name' => 'category.update', 'module' => 'category', 'action' => 'update'],
            ['name' => 'category.delete', 'module' => 'category', 'action' => 'delete'],
            
            // Product Permissions
            ['name' => 'product.view', 'module' => 'product', 'action' => 'view'],
            ['name' => 'product.create', 'module' => 'product', 'action' => 'create'],
            ['name' => 'product.update', 'module' => 'product', 'action' => 'update'],
            ['name' => 'product.delete', 'module' => 'product', 'action' => 'delete'],
            ['name' => 'product.show', 'module' => 'product', 'action' => 'show'],
            
            // Wishlist Permissions
            ['name' => 'wishlist.view', 'module' => 'wishlist', 'action' => 'view'],
            ['name' => 'wishlist.create', 'module' => 'wishlist', 'action' => 'create'],
            ['name' => 'wishlist.delete', 'module' => 'wishlist', 'action' => 'delete'],
            
            // Checkout Permissions
            ['name' => 'checkout.view', 'module' => 'checkout', 'action' => 'view'],
            ['name' => 'checkout.create', 'module' => 'checkout', 'action' => 'create'],
            
            // Order Permissions
            ['name' => 'order.view', 'module' => 'order', 'action' => 'view'],
            ['name' => 'order.create', 'module' => 'order', 'action' => 'create'],
            ['name' => 'order.update', 'module' => 'order', 'action' => 'update'],
            ['name' => 'order.shipment', 'module' => 'order', 'action' => 'shipment'],
            
            // Payment Permissions
            ['name' => 'payment.view', 'module' => 'payment', 'action' => 'view'],
            ['name' => 'payment.create', 'module' => 'payment', 'action' => 'create'],
            ['name' => 'payment.update', 'module' => 'payment', 'action' => 'update'],
            
            // Wallet Permissions
            ['name' => 'wallet.view', 'module' => 'wallet', 'action' => 'view'],
            ['name' => 'wallet.withdraw', 'module' => 'wallet', 'action' => 'withdraw'],
            
            // Review Permissions
            ['name' => 'review.create', 'module' => 'review', 'action' => 'create'],
            ['name' => 'review.update', 'module' => 'review', 'action' => 'update'],
            ['name' => 'review.delete', 'module' => 'review', 'action' => 'delete'],
            
            // Coupon Permissions
            ['name' => 'coupon.view', 'module' => 'coupon', 'action' => 'view'],
            ['name' => 'coupon.create', 'module' => 'coupon', 'action' => 'create'],
            ['name' => 'coupon.update', 'module' => 'coupon', 'action' => 'update'],
            ['name' => 'coupon.delete', 'module' => 'coupon', 'action' => 'delete'],
            ['name' => 'coupon.apply', 'module' => 'coupon', 'action' => 'apply'],
            
            // Vendor Permissions
            ['name' => 'vendor.view', 'module' => 'vendor', 'action' => 'view'],
            ['name' => 'vendor.create', 'module' => 'vendor', 'action' => 'create'],
            ['name' => 'vendor.update', 'module' => 'vendor', 'action' => 'update'],
            ['name' => 'vendor.delete', 'module' => 'vendor', 'action' => 'delete'],
            ['name' => 'vendor.approve', 'module' => 'vendor', 'action' => 'approve'],
            ['name' => 'vendor.dashboard.view', 'module' => 'vendor', 'action' => 'dashboard.view'],
            ['name' => 'vendor.documents.view', 'module' => 'vendor', 'action' => 'documents.view'],
            ['name' => 'vendor.documents.create', 'module' => 'vendor', 'action' => 'documents.create'],
            ['name' => 'vendor.inventory.view', 'module' => 'vendor', 'action' => 'inventory.view'],
            
            // Attribute Permissions
            ['name' => 'attribute.view', 'module' => 'attribute', 'action' => 'view'],
            ['name' => 'attribute.create', 'module' => 'attribute', 'action' => 'create'],
            ['name' => 'attribute.update', 'module' => 'attribute', 'action' => 'update'],
            ['name' => 'attribute.delete', 'module' => 'attribute', 'action' => 'delete'],
            
            // Variant Permissions
            ['name' => 'variant.view', 'module' => 'variant', 'action' => 'view'],
            ['name' => 'variant.create', 'module' => 'variant', 'action' => 'create'],
            ['name' => 'variant.update', 'module' => 'variant', 'action' => 'update'],
            ['name' => 'variant.delete', 'module' => 'variant', 'action' => 'delete'],
            
            // Commission Permissions
            ['name' => 'commission.view', 'module' => 'commission', 'action' => 'view'],
            ['name' => 'commission.create', 'module' => 'commission', 'action' => 'create'],
            ['name' => 'commission.update', 'module' => 'commission', 'action' => 'update'],
            ['name' => 'commission.delete', 'module' => 'commission', 'action' => 'delete'],
            
            // Withdraw Permissions
            ['name' => 'withdraw.view', 'module' => 'withdraw', 'action' => 'view'],
            ['name' => 'withdraw.approve', 'module' => 'withdraw', 'action' => 'approve'],
            ['name' => 'withdraw.reject', 'module' => 'withdraw', 'action' => 'reject'],
            
            // Banner Permissions
            ['name' => 'banner.view', 'module' => 'banner', 'action' => 'view'],
            ['name' => 'banner.create', 'module' => 'banner', 'action' => 'create'],
            ['name' => 'banner.update', 'module' => 'banner', 'action' => 'update'],
            ['name' => 'banner.delete', 'module' => 'banner', 'action' => 'delete'],
            
            // Analytics Permissions
            ['name' => 'analytics.view', 'module' => 'analytics', 'action' => 'view'],
            
            // Report Permissions
            ['name' => 'report.view', 'module' => 'report', 'action' => 'view'],
            
            // Dashboard Permissions
            ['name' => 'dashboard.view', 'module' => 'dashboard', 'action' => 'view'],
            
            // Support Permissions
            ['name' => 'support.view', 'module' => 'support', 'action' => 'view'],
            ['name' => 'support.create', 'module' => 'support', 'action' => 'create'],
            ['name' => 'support.update', 'module' => 'support', 'action' => 'update'],
            ['name' => 'support.reply', 'module' => 'support', 'action' => 'reply'],
            
            // Role Permissions
            ['name' => 'role.view', 'module' => 'role', 'action' => 'view'],
            ['name' => 'role.create', 'module' => 'role', 'action' => 'create'],
            ['name' => 'role.update', 'module' => 'role', 'action' => 'update'],
            ['name' => 'role.delete', 'module' => 'role', 'action' => 'delete'],
            
            // Permission Permissions
            ['name' => 'permission.view', 'module' => 'permission', 'action' => 'view'],
            ['name' => 'permission.create', 'module' => 'permission', 'action' => 'create'],
            ['name' => 'permission.update', 'module' => 'permission', 'action' => 'update'],
            ['name' => 'permission.delete', 'module' => 'permission', 'action' => 'delete'],
            
            // User Management Permissions
            ['name' => 'user.view', 'module' => 'user', 'action' => 'view'],
            ['name' => 'user.create', 'module' => 'user', 'action' => 'create'],
            ['name' => 'user.update', 'module' => 'user', 'action' => 'update'],
            ['name' => 'user.delete', 'module' => 'user', 'action' => 'delete'],
            ['name' => 'user.assign-role', 'module' => 'user', 'action' => 'assign-role'],
            
            // Profile List Permissions
            ['name' => 'profiles.view', 'module' => 'profiles', 'action' => 'view'],
            
            // Log Permissions
            ['name' => 'log.view', 'module' => 'log', 'action' => 'view'],
            
            // Settings Permissions
            ['name' => 'settings.view', 'module' => 'settings', 'action' => 'view'],
            ['name' => 'settings.update', 'module' => 'settings', 'action' => 'update'],
            
            // Question Permissions
            ['name' => 'question.view', 'module' => 'question', 'action' => 'view'],
            ['name' => 'question.create', 'module' => 'question', 'action' => 'create'],
            ['name' => 'question.answer', 'module' => 'question', 'action' => 'answer'],
        ];

        foreach ($permissions as $perm) {
            Permission::updateOrCreate(
                ['name' => $perm['name']],
                [
                    'module' => $perm['module'],
                    'action' => $perm['action'],
                ]
            );
        }
    }
}