<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;
use Carbon\Carbon;

class SettingSeeder extends Seeder
{
    public function run()
    {
        $settings = [
            ['key' => 'site_name', 'value' => 'Soulful E-commerce', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['key' => 'site_email', 'value' => 'support@soulful.com', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['key' => 'site_phone', 'value' => '+91 9876543210', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['key' => 'tax_rate', 'value' => '18.00', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['key' => 'currency', 'value' => 'INR', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['key' => 'shipping_charge', 'value' => '50.00', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['key' => 'free_shipping_threshold', 'value' => '500.00', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['key' => 'vendor_commission_default', 'value' => '10.00', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['key' => 'max_product_images', 'value' => '5', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['key' => 'enable_reviews', 'value' => 'true', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ];

        foreach ($settings as $setting) {
            Setting::create($setting);
        }
    }
}