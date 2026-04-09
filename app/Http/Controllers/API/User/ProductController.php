<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{

    // GET /products
    public function index(Request $request)
    {

        $query = Product::with(['category','brand','images'])
            ->where('status',1)
            ->where('is_approved',1);



        // category filter
        if($request->category){
            $query->where('category_id',$request->category);
        }

        // price filter
        if($request->price_min){
            $query->where('price','>=',$request->price_min);
        }

        if($request->price_max){
            $query->where('price','<=',$request->price_max);
        }

        $products = $query->paginate(10);

        return response()->json([
            'success'=>true,
            'data'=>$products
        ]);
    }


    // GET /products/{slug}
public function show($slug)
{
    $product = Product::with(['category','brand','images','reviews','vendor','variants.attributeValues.attribute','specifications'])
        ->where('slug',$slug)
        ->where('status',1)
        ->where('is_approved',1)
        ->firstOrFail();
//         $product = Product::with([
//     'category',
//     'vendor',
//     'images',
//     'variants.attributeValues.attribute',
//     'specifications' // ✅ ADD THIS
// ])->findOrFail($id);

    return response()->json([
        'success'=>true,
        'data'=>$product
    ]);
}

// GET /products/search
public function search(Request $request)
{
    $query = $request->q;

    $products = Product::with('images')
        ->where('name','LIKE',"%$query%")
        ->where('status',1)
        ->where('is_approved',1)
        ->paginate(10);

    return response()->json([
        'success'=>true,
        'data'=>$products
    ]);
}

// GET /products/featured
public function featured()
{
    $products = Product::with('images')
        ->where('is_featured',1)
        ->where('status',1)
        ->where('is_approved',1)
        ->take(10)
        ->get();

    return response()->json([
        'success'=>true,
        'data'=>$products
    ]);
} 


// GET /products/latest
public function latest()
{
    $products = Product::with('images')
        ->where('status',1)
        ->where('is_approved',1)
        ->latest()
        ->take(10)
        ->get();

    return response()->json([
        'success'=>true,
        'data'=>$products
    ]);
}

// GET /products/deals
public function deals()
{
    $products = Product::with('images')
        ->whereNotNull('discount_price')
        ->where('status',1)
        ->where('is_approved',1)
        ->get();

    return response()->json([
        'success'=>true,
        'data'=>$products
    ]);
}

// GET /products/best-sellers
public function bestSellers()
{
    $products = Product::with('images')
        ->where('status',1)
        ->where('is_approved',1)
        ->orderBy('stock','DESC')
        ->take(10)
        ->get();

    return response()->json([
        'success'=>true,
        'data'=>$products
    ]);
}

// GET /products/{id}/related
public function related($id)
{
    $product = Product::findOrFail($id);

    $related = Product::with('images')
        ->where('category_id',$product->category_id)
        ->where('id','!=',$id)
        ->take(8)
        ->get();

    return response()->json([
        'success'=>true,
        'data'=>$related
    ]);
}

public function relatedBulk(Request $request)
{
    // ✅ Validate input
    if (!$request->ids) {
        return response()->json([
            'success' => false,
            'message' => 'Product IDs are required'
        ], 400);
    }

    // ✅ Convert IDs to array
    $ids = explode(',', $request->ids);

    // ✅ Get selected products
    $products = Product::whereIn('id', $ids)->get();

    if ($products->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'No valid products found'
        ], 404);
    }

    // ✅ Collect filters
    $categoryIds = $products->pluck('category_id')->unique();
    $brandIds = $products->pluck('brand_id')->unique();
    $avgPrice = $products->avg('price');

    // ✅ Price range (±30%)
    $minPrice = $avgPrice * 0.7;
    $maxPrice = $avgPrice * 1.3;

    // =========================================
    // 🔥 PRIMARY QUERY (Smart Recommendation)
    // =========================================
    $related = Product::with('images')
        ->where(function ($query) use ($categoryIds, $brandIds) {
            $query->whereIn('category_id', $categoryIds)
                  ->orWhereIn('brand_id', $brandIds);
        })
        ->whereNotIn('id', $ids)
        ->where('status', 1)
        ->where('is_approved', 1)
        ->whereBetween('price', [$minPrice, $maxPrice])
        ->take(12)
        ->get();

    // =========================================
    // 🔥 FALLBACK 1 (Category only)
    // =========================================
    if ($related->isEmpty()) {
        $related = Product::with('images')
            ->whereIn('category_id', $categoryIds)
            ->whereNotIn('id', $ids)
            ->where('status', 1)
            ->where('is_approved', 1)
            ->take(12)
            ->get();
    }

    // =========================================
    // 🔥 FALLBACK 2 (Latest products)
    // =========================================
    if ($related->isEmpty()) {
        $related = Product::with('images')
            ->where('status', 1)
            ->where('is_approved', 1)
            ->latest()
            ->take(12)
            ->get();
    }

    // ✅ Final Response
    return response()->json([
        'success' => true,
        'filters_used' => [
            'categories' => $categoryIds,
            'brands' => $brandIds,
            'price_range' => [round($minPrice, 2), round($maxPrice, 2)]
        ],
        'data' => $related
    ]);
}

// GET /products/{id}/images
public function images($id)
{
    $images = \App\Models\ProductImage::where('product_id',$id)
        ->orderBy('sort_order')
        ->get();

    return response()->json([
        'success'=>true,
        'data'=>$images
    ]);
}

// GET /products/{id}/reviews
public function reviews($id)
{
    $reviews = \App\Models\Review::where('product_id',$id)
        ->latest()
        ->get();

    return response()->json([
        'success'=>true,
        'data'=>$reviews
    ]);
}

// GET /products/{id}/rating
public function rating($id)
{
    $rating = \App\Models\Review::where('product_id',$id)
        ->avg('rating');

    return response()->json([
        'success'=>true,
        'rating'=>round($rating,1)
    ]);
}
}