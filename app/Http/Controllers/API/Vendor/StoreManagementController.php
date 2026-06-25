<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StoreManagementController extends Controller
{
    public function index()
    {
        $vendor = auth()->user()->vendor;

        $vendor->load([
            'storeBanners'
        ]);

        return response()->json([
            'success' => true,

            'profile' => [
                'store_name' => $vendor->store_name,
                'store_slug' => $vendor->store_slug,
                'store_logo' => $vendor->store_logo,
                'store_banner' => $vendor->store_banner,
                'description' => $vendor->description,
                'store_about' => $vendor->store_about,
                'theme_color' => $vendor->theme_color,

                'facebook_url' => $vendor->facebook_url,
                'instagram_url' => $vendor->instagram_url,
                'twitter_url' => $vendor->twitter_url,
                'youtube_url' => $vendor->youtube_url,
            ],

            'analytics' => [
                'products' =>
                    $vendor->products()->count(),

                'followers' =>
                    method_exists($vendor, 'followers')
                        ? $vendor->followers()->count()
                        : 0,

                'rating' =>
                    $vendor->rating ?? 0,
            ],

            'banners' =>
                $vendor->storeBanners,

            'store_status' =>
                $vendor->status,
        ]);
    }
    public function save(Request $request)
{
    $vendor = auth()->user()->vendor;

    $request->validate([
        'store_name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'store_about' => 'nullable|string',

        'theme_color' => 'nullable|string|max:20',

        'facebook_url' => 'nullable|string|max:255',
        'instagram_url' => 'nullable|string|max:255',
        'twitter_url' => 'nullable|string|max:255',
        'youtube_url' => 'nullable|string|max:255',

        'store_logo' => 'nullable|image|max:5120',
        'store_banner' => 'nullable|image|max:5120',
    ]);

    if ($request->hasFile('store_logo')) {

        ImageUploadService::deleteImage(
            $vendor->store_logo
        );

        $vendor->store_logo =
            ImageUploadService::uploadWebp(
                $request->file('store_logo'),
                'vendors/logo',
                600,
                90
            );
    }

    if ($request->hasFile('store_banner')) {

        ImageUploadService::deleteImage(
            $vendor->store_banner
        );

        $vendor->store_banner =
            ImageUploadService::uploadWebp(
                $request->file('store_banner'),
                'vendors/banner',
                1920,
                90
            );
    }

    $vendor->store_name = $request->store_name;
    $vendor->description = $request->description;
    $vendor->store_about = $request->store_about;

    $vendor->theme_color = $request->theme_color;

    $vendor->facebook_url = $request->facebook_url;
    $vendor->instagram_url = $request->instagram_url;
    $vendor->twitter_url = $request->twitter_url;
    $vendor->youtube_url = $request->youtube_url;

    $vendor->save();

    return response()->json([
        'success' => true,
        'message' => 'Store updated successfully.',
        'data' => $vendor
    ]);
}

}