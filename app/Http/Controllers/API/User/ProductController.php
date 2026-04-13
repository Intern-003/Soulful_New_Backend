<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // =========================
    // BASE PRODUCT QUERY (REUSABLE)
    // =========================
    private function baseProductQuery()
    {
        return Product::select('products.*')
            // ⭐ average rating
            ->selectRaw('(
                SELECT AVG(rating)
                FROM reviews
                WHERE reviews.product_id = products.id
                AND reviews.status = 1
            ) as average_rating')

            // ⭐ total reviews
            ->selectRaw('(
                SELECT COUNT(*)
                FROM reviews
                WHERE reviews.product_id = products.id
                AND reviews.status = 1
            ) as total_reviews')

            ->with(['category', 'brand', 'images', 'variants'])
            ->where('status', 1)
            ->where('is_approved', 1);
    }

    // =========================
    // GET /products
    // =========================
    public function index(Request $request)
    {
        $query = $this->baseProductQuery();

        if ($request->category) {
            $query->where('category_id', $request->category);
        }

        if ($request->price_min) {
            $query->where('price', '>=', $request->price_min);
        }

        if ($request->price_max) {
            $query->where('price', '<=', $request->price_max);
        }

        $products = $query->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    // =========================
    // GET /products/{slug}
    // =========================
    public function show($slug)
    {
        $product = $this->baseProductQuery()
            ->with([
                'reviews' => function ($q) {
                    $q->where('status', 1)
                        ->latest()
                        ->with('user:id,name');
                },
                'vendor',
                'variants.attributeValues.attribute',
                'specifications'
            ])
            ->where('slug', $slug)
            ->firstOrFail();

        // =========================
        // VARIANT TRANSFORMATION (KEEP RAW IMAGE)
        // =========================
        if ($product->variants) {
            $product->variants->transform(function ($variant) {

                $grouped = [];

                foreach ($variant->attributeValues as $value) {
                    $grouped[$value->attribute->name] = $value->value;
                }

                $variant->attributes = $grouped;

                unset($variant->attributeValues);

                return $variant;
            });
        }

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    // =========================
    // SEARCH
    // =========================
    public function search(Request $request)
    {
        $products = $this->baseProductQuery()
            ->where('name', 'LIKE', "%{$request->q}%")
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    // =========================
    // FEATURED
    // =========================
    public function featured()
    {
        $products = $this->baseProductQuery()
            ->where('is_featured', 1)
            ->take(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    // =========================
    // LATEST
    // =========================
    public function latest()
    {
        $products = $this->baseProductQuery()
            ->latest()
            ->take(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    // =========================
    // DEALS
    // =========================
    public function deals()
    {
        $products = $this->baseProductQuery()
            ->whereNotNull('discount_price')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    // =========================
    // BEST SELLERS
    // =========================
    public function bestSellers()
    {
        $products = $this->baseProductQuery()
            ->orderBy('stock', 'DESC')
            ->take(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    // =========================
    // RELATED
    // =========================
    public function related($id)
    {
        $product = Product::findOrFail($id);

        $related = $this->baseProductQuery()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $id)
            ->take(8)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $related
        ]);
    }

    // =========================
    // RELATED BULK
    // =========================
    public function relatedBulk(Request $request)
    {
        if (!$request->ids) {
            return response()->json([
                'success' => false,
                'message' => 'Product IDs are required'
            ], 400);
        }

        $ids = explode(',', $request->ids);

        $products = Product::whereIn('id', $ids)->get();

        if ($products->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No valid products found'
            ], 404);
        }

        $categoryIds = $products->pluck('category_id')->unique();
        $brandIds = $products->pluck('brand_id')->unique();
        $avgPrice = $products->avg('price');

        $minPrice = $avgPrice * 0.7;
        $maxPrice = $avgPrice * 1.3;

        $related = $this->baseProductQuery()
            ->where(function ($q) use ($categoryIds, $brandIds) {
                $q->whereIn('category_id', $categoryIds)
                  ->orWhereIn('brand_id', $brandIds);
            })
            ->whereNotIn('id', $ids)
            ->whereBetween('price', [$minPrice, $maxPrice])
            ->take(12)
            ->get();

        if ($related->isEmpty()) {
            $related = $this->baseProductQuery()
                ->whereIn('category_id', $categoryIds)
                ->whereNotIn('id', $ids)
                ->take(12)
                ->get();
        }

        if ($related->isEmpty()) {
            $related = $this->baseProductQuery()
                ->latest()
                ->take(12)
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => $related
        ]);
    }

    // =========================
    // IMAGES
    // =========================
    public function images($id)
    {
        $images = \App\Models\ProductImage::where('product_id', $id)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $images
        ]);
    }

    // =========================
    // REVIEWS
    // =========================
    public function reviews($id)
    {
        $reviews = \App\Models\Review::where('product_id', $id)
            ->where('status', 1)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reviews
        ]);
    }

    // =========================
    // RATING ONLY
    // =========================
    public function rating($id)
    {
        $rating = \App\Models\Review::where('product_id', $id)
            ->where('status', 1)
            ->avg('rating');

        return response()->json([
            'success' => true,
            'rating' => round($rating, 1)
        ]);
    }
}