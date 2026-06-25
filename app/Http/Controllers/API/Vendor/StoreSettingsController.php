<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ImageUploadService;

class StoreSettingsController extends Controller
{
    public function update(Request $request)
    {
        $vendor = auth()->user()->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor profile not found.'
            ], 404);
        }

        $request->validate([
            'store_name'     => 'sometimes|string|max:255',
            'description'    => 'nullable|string',
            'store_about'    => 'nullable|string',

            'store_logo'     => 'nullable|image|max:5120',
            'store_banner'   => 'nullable|image|max:5120',

            'theme_color'    => 'nullable|string|max:20',

            'facebook_url'   => 'nullable|url|max:255',
            'instagram_url'  => 'nullable|url|max:255',
            'twitter_url'    => 'nullable|url|max:255',
            'youtube_url'    => 'nullable|url|max:255',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Store Logo Upload
        |--------------------------------------------------------------------------
        */
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

        /*
        |--------------------------------------------------------------------------
        | Store Banner Upload
        |--------------------------------------------------------------------------
        */
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

        /*
        |--------------------------------------------------------------------------
        | Partial Update Support
        |--------------------------------------------------------------------------
        | Allows:
        | - Store Information Form
        | - Branding Form
        | - Social Links Form
        | To update independently without
        | overwriting other fields.
        |--------------------------------------------------------------------------
        */
        $vendor->fill(
            $request->only([
                'store_name',
                'description',
                'store_about',
                'theme_color',
                'facebook_url',
                'instagram_url',
                'twitter_url',
                'youtube_url',
            ])
        );

        $vendor->save();

        return response()->json([
            'success' => true,
            'message' => 'Store settings updated successfully.',
            'data'    => [
                'id'             => $vendor->id,
                'store_name'     => $vendor->store_name,
                'store_slug'     => $vendor->store_slug,
                'store_logo'     => $vendor->store_logo,
                'store_banner'   => $vendor->store_banner,
                'description'    => $vendor->description,
                'store_about'    => $vendor->store_about,
                'theme_color'    => $vendor->theme_color,

                'facebook_url'   => $vendor->facebook_url,
                'instagram_url'  => $vendor->instagram_url,
                'twitter_url'    => $vendor->twitter_url,
                'youtube_url'    => $vendor->youtube_url,

                'status'         => $vendor->status,
                'rating'         => $vendor->rating,
                'updated_at'     => $vendor->updated_at,
            ]
        ]);
    }
}