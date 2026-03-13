<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\Product;
use App\Models\Review;

class VendorStoreController extends Controller
{

    // GET /vendors
    public function index()
    {
        $vendors = Vendor::where('status','approved')
            ->orderBy('rating','desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $vendors
        ]);
    }


    // GET /vendors/{slug}
    public function show($slug)
    {
        $vendor = Vendor::where('store_slug',$slug)
            ->where('status','approved')
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $vendor
        ]);
    }


    // GET /vendors/{slug}/products
    public function products($slug)
    {
        $vendor = Vendor::where('store_slug',$slug)->firstOrFail();

        $products = Product::with('images')
            ->where('vendor_id',$vendor->id)
            ->where('status',1)
            ->where('is_approved',1)
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }


    // GET /vendors/{slug}/reviews
    public function reviews($slug)
    {
        $vendor = Vendor::where('store_slug',$slug)->firstOrFail();

        $reviews = Review::whereHas('product', function ($query) use ($vendor) {
            $query->where('vendor_id',$vendor->id);
        })->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $reviews
        ]);
    }

}