<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;

class VendorStoreController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Vendor Listing
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $vendors = Vendor::query()
            ->where('status', 'approved')
            ->orderByDesc('rating')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $vendors,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Vendor Details
    |--------------------------------------------------------------------------
    */

    public function show($slug)
    {
        $vendor = Vendor::query()
            ->where('store_slug', $slug)
            ->where('status', 'approved')
            ->firstOrFail();

        $totalProducts = Product::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', 1)
            ->where('approval_status', 'approved')
            ->count();

        return response()->json([
            'success' => true,

            'data' => [
                'vendor' => $vendor,

                'stats' => [
                    'products' => $totalProducts,
                    'rating' => $vendor->rating ?? 0,
                    'followers' => $vendor->followers()->count(),
                ],
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Vendor Products
    |--------------------------------------------------------------------------
    */

    public function products($slug)
    {
        $vendor = Vendor::query()
            ->where('store_slug', $slug)
            ->firstOrFail();

        $products = Product::with([
                'images',
                'category:id,name,slug',
            ])
            ->where('vendor_id', $vendor->id)
            ->where('status', 1)
            ->where('approval_status', 'approved')
            ->latest()
            ->paginate(12);

        $categories = Product::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', 1)
            ->where('approval_status', 'approved')
            ->with('category:id,name,slug')
            ->get()
            ->pluck('category')
            ->filter()
            ->unique('id')
            ->values();

        return response()->json([
            'success' => true,
            'vendor' => $vendor,
            'categories' => $categories,
            'products' => $products,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Vendor Reviews
    |--------------------------------------------------------------------------
    */

public function reviews($slug)
{
    $vendor = Vendor::where(
        'store_slug',
        $slug
    )->firstOrFail();

    $reviews = Review::with([
        'user:id,name',
        'product:id,name'
    ])
    ->whereHas(
        'product',
        function ($query) use ($vendor) {
            $query->where(
                'vendor_id',
                $vendor->id
            );
        }
    )
    ->where('status', true)
    ->latest()
    ->get();

    $totalReviews = $reviews->count();

    $averageRating = round(
        $reviews->avg('rating') ?? 0,
        1
    );

    $ratingBreakdown = [
        5 => $reviews->where('rating', 5)->count(),
        4 => $reviews->where('rating', 4)->count(),
        3 => $reviews->where('rating', 3)->count(),
        2 => $reviews->where('rating', 2)->count(),
        1 => $reviews->where('rating', 1)->count(),
    ];

    return response()->json([
        'success' => true,

        'summary' => [
            'average_rating' => $averageRating,
            'total_reviews' => $totalReviews,
            'rating_breakdown' => $ratingBreakdown,
        ],

        'reviews' => $reviews->map(function ($review) {
            return [
                'id' => $review->id,

                'rating' => $review->rating,

                'title' => $review->title,

                'review' => $review->review,

                'created_at' => $review->created_at,

                'verified_purchase' => true,

                'user' => [
                    'id' => $review->user?->id,
                    'name' => $review->user?->name,
                ],

                'product' => [
                    'id' => $review->product?->id,
                    'name' => $review->product?->name,
                ],
            ];
        })->values(),
    ]);
}

    /*
    |--------------------------------------------------------------------------
    | Vendor Store Homepage
    |--------------------------------------------------------------------------
    */

    public function homepage(Request $request, $slug)
    {
        $vendor = Vendor::with([
                'storeBanners',
                'storeSections',
            ])
            ->where('store_slug', $slug)
            ->where('status', 'approved')
            ->firstOrFail();

        $products = Product::with([
                'images',
                'category',
            ])
            ->where('vendor_id', $vendor->id)
            ->where('status', 1)
            ->where('approval_status', 'approved')
            ->latest()
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Featured Products
        |--------------------------------------------------------------------------
        */

        $featuredProducts = $products
            ->where('is_featured', true)
            ->values();

        /*
        |--------------------------------------------------------------------------
        | New Arrivals
        |--------------------------------------------------------------------------
        */

        $newArrivals = $products
            ->sortByDesc('created_at')
            ->take(12)
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Categories
        |--------------------------------------------------------------------------
        */

        $categories = $products
            ->pluck('category')
            ->filter()
            ->unique('id')
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Follow Status
        |--------------------------------------------------------------------------
        */

        $isFollowing = false;

        if (auth('sanctum')->check()) {

            $user = auth('sanctum')->user();

            $isFollowing = $vendor
                ->followers()
                ->where('user_id', $user->id)
                ->exists();
        }

        /*
        |--------------------------------------------------------------------------
        | Theme Color
        |--------------------------------------------------------------------------
        */

        $themeColor = $vendor->theme_color ?: '#7a1c3d';

        return response()->json([
            'success' => true,

            'vendor' => [
                'id' => $vendor->id,

                'store_name' => $vendor->store_name,
                'store_slug' => $vendor->store_slug,

                'store_logo' => $vendor->store_logo,
                'store_banner' => $vendor->store_banner,

                'description' => $vendor->description,
                'store_about' => $vendor->store_about,

                'facebook_url' => $vendor->facebook_url,
                'instagram_url' => $vendor->instagram_url,
                'twitter_url' => $vendor->twitter_url,
                'youtube_url' => $vendor->youtube_url,

                'rating' => $vendor->rating,

                'theme_color' => $themeColor,

                'created_at' => $vendor->created_at,

                'is_following' => $isFollowing,
            ],

            'banners' => $vendor->storeBanners,

            'sections' => $vendor->storeSections,

            'categories' => $categories,

            'featured_products' => $featuredProducts,

            'new_arrivals' => $newArrivals,

            'total_products' => $products->count(),

            'followers_count' => $vendor->followers()->count(),
        ]);
    }

    
}