<?php

namespace App\Http\Controllers\API\Common;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Carbon\Carbon;

class BannerController extends Controller
{
    // ====================================
    // PUBLIC ACTIVE BANNERS
    // ====================================
    public function getBanners()
    {
        $now = Carbon::now();

        $banners = Banner::with([
            'products' => function ($q) {
                $q->select(
                    'products.id',
                    'products.name',
                    'products.price',
                    'products.status'
                )->with([
                    'images' => function ($img) {
                        $img->select(
                            'id',
                            'product_id',
                            'image_url',
                            'is_primary'
                        );
                    }
                ]);
            }
        ])
            ->where('status', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('start_date')
                    ->orWhere('start_date', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $now);
            })
            ->orderBy('position', 'asc')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $banners
        ]);
    }
}