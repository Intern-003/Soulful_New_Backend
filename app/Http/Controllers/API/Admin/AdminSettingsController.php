<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;

class AdminSettingsController extends Controller
{
    // ✅ GET /admin/settings
    public function index()
    {
        $settings = cache()->remember('settings', 60, function () {
    return Setting::all()->pluck('value', 'key');
});

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    // ✅ PUT /admin/settings
    public function update(Request $request)
    {
        // ✅ Validation (important)
        $validated = $request->validate([
            // General
            'site_name' => 'nullable|string|max:255',
            'site_email' => 'nullable|email|max:255',
            'site_phone' => 'nullable|string|max:20',

            // Business
            'tax_rate' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',

            // Shipping
            'shipping_charge' => 'nullable|numeric|min:0',
            'free_shipping_threshold' => 'nullable|numeric|min:0',

            // Vendor
            'vendor_commission' => 'nullable|numeric|min:0|max:100',

            // Product
            'max_product_images' => 'nullable|integer|min:1',

            // Features
            'enable_reviews' => 'nullable|boolean',
        ]);

        // ✅ Store / Update settings
        foreach ($validated as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => is_bool($value) ? (int) $value : $value
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully'
        ]);
    }
}