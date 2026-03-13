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
    $product = Product::with(['category','brand','images','reviews'])
        ->where('slug',$slug)
        ->where('status',1)
        ->where('is_approved',1)
        ->firstOrFail();

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