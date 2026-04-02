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

        // ==================== ADMIN PERMISSIONS ====================
        // Admin gets ALL permissions
        $allPermissions = Permission::pluck('id');
        $admin->permissions()->sync($allPermissions);

        // ==================== USER PERMISSIONS ====================
        $userPermissions = Permission::whereIn('name', [
            // Auth & Profile
            'auth.logout', 'auth.refresh', 'auth.verify', 'profile.view', 'profile.update',
            
            // Address
            'address.view', 'address.create', 'address.update', 'address.delete',
            
            // Cart
            'cart.view', 'cart.create', 'cart.update', 'cart.delete',
            
            // Category (view only)
            'category.view',
            
            // Product (view only)
            'product.view', 'product.show',
            
            // Wishlist
            'wishlist.view', 'wishlist.create', 'wishlist.delete',
            
            // Checkout
            'checkout.view', 'checkout.create',
            
            // Order
            'order.view', 'order.create', 'order.update',
            
            // Payment
            'payment.view', 'payment.create', 'payment.update',
            
            // Wallet
            'wallet.view',
            
            // Review
            'review.create', 'review.update', 'review.delete',
            
            // Coupon
            'coupon.view', 'coupon.apply',
            
            // Vendor (view only)
            'vendor.view',
            
            // Support
            'support.view', 'support.create', 'support.update',
        ])->pluck('id');
        
        $user->permissions()->sync($userPermissions);

        // ==================== VENDOR PERMISSIONS ====================
        $vendorPermissions = Permission::whereIn('name', [
            // Auth & Profile
            'auth.logout', 'auth.refresh', 'auth.verify', 'profile.view', 'profile.update',
            
            // Address
            'address.view', 'address.create', 'address.update', 'address.delete',
            
            // Cart
            'cart.view', 'cart.create', 'cart.update', 'cart.delete',
            
            // Category (view only)
            'category.view',
            
            // Product (full CRUD for own products)
            'product.view', 'product.show', 'product.create', 'product.update', 'product.delete',
            
            // Wishlist
            'wishlist.view', 'wishlist.create', 'wishlist.delete',
            
            // Checkout (for buying as customer)
            'checkout.view', 'checkout.create',
            
            // Order (view own store orders + manage)
            'order.view', 'order.update', 'order.shipment',
            
            // Payment
            'payment.view', 'payment.create', 'payment.update',
            
            // Wallet (vendor wallet)
            'wallet.view', 'wallet.withdraw',
            
            // Review (view and reply)
            'review.view', 'review.create', 'review.update',
            
            // Coupon (vendor coupons)
            'coupon.view', 'coupon.create', 'coupon.update', 'coupon.delete', 'coupon.apply',
            
            // Vendor (vendor specific)
            'vendor.view', 'vendor.dashboard.view', 'vendor.documents.view', 'vendor.documents.create',
            'vendor.inventory.view',
            
            // Variant (product variants)
            'variant.view', 'variant.create', 'variant.update', 'variant.delete',
            
            // Question (product Q&A)
            'question.view', 'question.create', 'question.answer',
            
            // Support
            'support.view', 'support.create', 'support.update',
            
            // Reports (vendor sales reports)
            'report.view',
        ])->pluck('id');
        
        $vendor->permissions()->sync($vendorPermissions);
    }
}