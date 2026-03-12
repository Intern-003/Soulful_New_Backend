<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            // Order matters for foreign keys
            RoleSeeder::class,
            PermissionSeeder::class,
            UserSeeder::class,
            RolePermissionSeeder::class,
            UserProfileSeeder::class,
            AddressSeeder::class,
            VendorSeeder::class,
            VendorDocumentSeeder::class,
            VendorWalletSeeder::class,
            WithdrawRequestSeeder::class,
            CategorySeeder::class,
            BrandSeeder::class,
            ProductSeeder::class,
            ProductImageSeeder::class,
            TagSeeder::class,
            ProductTagSeeder::class,
            AttributeSeeder::class,
            AttributeValueSeeder::class,
            ProductVariantSeeder::class,
            ProductVariantAttributeSeeder::class,
            CartSeeder::class,
            CartItemSeeder::class,
            WishlistSeeder::class,
            OrderSeeder::class,
            OrderItemSeeder::class,
            PaymentSeeder::class,
            ShipmentSeeder::class,
            ReviewSeeder::class,
            CouponSeeder::class,
            NotificationSeeder::class,
            SettingSeeder::class,
            ActivityLogSeeder::class,
        ]);
    }
}