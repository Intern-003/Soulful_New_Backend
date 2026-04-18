<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // =========================
    // BASE PRODUCT QUERY
    // =========================
    private function baseProductQuery()
    {
        return Product::select('products.*')
            ->selectRaw('(
                SELECT AVG(rating)
                FROM reviews
                WHERE reviews.product_id = products.id
                AND reviews.status = 1
            ) as average_rating')
            ->selectRaw('(
                SELECT COUNT(*)
                FROM reviews
                WHERE reviews.product_id = products.id
                AND reviews.status = 1
            ) as total_reviews')
            ->with(['category', 'brand', 'images', 'variants'])
            ->where('status', 1)
            ->where('approval_status', 'approved');
    }

    // =========================
    // SAFE PRODUCT QUERY
    // =========================
    private function safeProductQuery()
    {
        return Product::select('products.*')
            ->with(['category', 'brand', 'images', 'variants'])
            ->where('status', 1)
            ->where('approval_status', 'approved');
    }

    // =========================
    // INDEX
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

        return response()->json([
            'success' => true,
            'data' => $query->paginate(10)
        ]);
    }

    // =========================
    // SHOW PRODUCT
    // =========================
    public function show($identifier)
    {
        $product = $this->safeProductQuery()
            ->with([
                'reviews' => function ($q) {
                    $q->where('status', 1)
                        ->latest()
                        ->with('user:id,name');
                },
                'vendor',
                'variants.attributeValues.attribute',
                'variants.images',
                'specifications'
            ])
            ->where(function ($query) use ($identifier) {
                $query->where('slug', $identifier)
                    ->orWhere('id', $identifier);
            })
            ->firstOrFail();

        // =========================
        // PRODUCT RATINGS
        // =========================
        $product->average_rating = $product->reviews->avg('rating');
        $product->total_reviews = $product->reviews->count();

        // =========================
        // FORMAT VARIANTS
        // =========================
        $product->variants->transform(function ($variant) {

            // -------- ATTRIBUTES --------
            $grouped = [];

            foreach ($variant->attributeValues as $value) {
                $grouped[$value->attribute->name][] = [
                    'value' => $value->value,
                    'hex'   => $value->hex_code
                ];
            }

            $variant->attributes = $grouped;
            unset($variant->attributeValues);

            // -------- IMAGES --------
            $variant->images->transform(function ($img) {
                $img->image_url = url($img->image);
                return $img;
            });

            // -------- PRIMARY IMAGE --------
            $primary = $variant->images->firstWhere('is_primary', 1);

            $variant->primary_image = $primary
                ? $primary->image_url
                : ($variant->images->first()->image_url ?? null);

            return $variant;
        });

        // =========================
        // PRODUCT IMAGES
        // =========================
        if ($product->images) {
            $product->images->transform(function ($img) {
                $img->image_url = url($img->image);
                return $img;
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
        return response()->json([
            'success' => true,
            'data' => $this->baseProductQuery()
                ->where('name', 'LIKE', "%{$request->q}%")
                ->paginate(10)
        ]);
    }

    // =========================
    // FEATURED
    // =========================
    public function featured()
    {
        return response()->json([
            'success' => true,
            'data' => $this->baseProductQuery()
                ->where('is_featured', 1)
                ->take(10)
                ->get()
        ]);
    }

    // =========================
    // LATEST
    // =========================
    public function latest()
    {
        return response()->json([
            'success' => true,
            'data' => $this->baseProductQuery()
                ->latest()
                ->take(10)
                ->get()
        ]);
    }

    // =========================
    // DEALS
    // =========================
    public function deals()
    {
        return response()->json([
            'success' => true,
            'data' => $this->baseProductQuery()
                ->whereNotNull('discount_price')
                ->get()
        ]);
    }

    // =========================
    // BEST SELLERS
    // =========================
    public function bestSellers()
    {
        return response()->json([
            'success' => true,
            'data' => $this->baseProductQuery()
                ->orderBy('stock', 'DESC')
                ->take(10)
                ->get()
        ]);
    }

    // =========================
    // RELATED
    // =========================
    public function related($id)
    {
        $product = Product::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->baseProductQuery()
                ->where('category_id', $product->category_id)
                ->where('id', '!=', $id)
                ->take(8)
                ->get()
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

        $images->transform(function ($img) {
            $img->image_url = url($img->image);
            return $img;
        });

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
        return response()->json([
            'success' => true,
            'data' => \App\Models\Review::where('product_id', $id)
                ->where('status', 1)
                ->latest()
                ->get()
        ]);
    }

    // =========================
    // RATING ONLY
    // =========================
    public function rating($id)
    {
        return response()->json([
            'success' => true,
            'rating' => round(
                \App\Models\Review::where('product_id', $id)
                    ->where('status', 1)
                    ->avg('rating'),
                1
            )
        ]);
    }
}