<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // =========================
    // BASE QUERY
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
            ->with([
                'category',
                'brand',
                'images',
                'variants.attributeValues.attribute',
                'variants.images',
                'specifications'
            ])
            ->where('status', 1)
            ->where('approval_status', 'approved');
    }

    // =========================
    // SAFE QUERY
    // =========================
    private function safeProductQuery()
    {
        return Product::select('products.*')
            ->with([
                'category',
                'brand',
                'images',
                'variants.attributeValues.attribute',
                'variants.images',
                'specifications'
            ])
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
                'reviews.user',
                'vendor',
                'user'
            ])
            ->where(function ($q) use ($identifier) {
                $q->where('slug', $identifier)
                    ->orWhere('id', $identifier);
            })
            ->firstOrFail();

        // =========================
        // RATINGS
        // =========================
        $product->average_rating = $product->reviews->avg('rating');
        $product->total_reviews = $product->reviews->count();

        // =========================
        // CREATOR
        // =========================
        if ($product->vendor_id && $product->vendor) {
            $product->creator = [
                'type' => 'vendor',
                'id' => $product->vendor->id,
                'name' => $product->vendor->store_name,
                'email' => $product->vendor->user->email ?? null
            ];
        } else {
            $product->creator = [
                'type' => 'user',
                'id' => $product->user->id ?? null,
                'name' => $product->user->name ?? null,
                'email' => $product->user->email ?? null
            ];
        }

        // =========================
        // VARIANTS
        // =========================
        $product->variants->transform(function ($variant) {

            // =========================
            // ATTRIBUTES (CLEAN UI FORMAT)
            // =========================
            $attributes = [];

            foreach ($variant->attributeValues as $value) {
                $name = $value->attribute->name ?? 'Attribute';

                $attributes[$name] = array_filter([
                    'value' => $value->value,
                    'hex' => $value->hex_code ?: null
                ]);
            }

            unset($variant->attributeValues);

            $variant->attributes = $attributes;

            // =========================
            // CORE CLEANUP ONLY
            // =========================
            $variant->discount_price = $variant->discount_price;
            $variant->barcode = $variant->barcode;
            $variant->weight = $variant->weight;

            // =========================
            // IMAGES (RAW DB FORMAT)
            // =========================
            $variant->images->transform(function ($img) {
                return [
                    'id' => $img->id,
                    'product_id' => $img->product_id,
                    'variant_id' => $img->variant_id,
                    'image_url' => $img->image_url, // raw DB path only
                    'is_primary' => $img->is_primary,
                    'sort_order' => $img->sort_order,
                ];
            });

            // =========================
            // PRIMARY IMAGE (RAW)
            // =========================
            $variant->primary_image = optional(
                $variant->images->firstWhere('is_primary', 1)
            )['image_url'] ?? optional($variant->images->first())['image_url'];

            return $variant;
        });

        // =========================
        // PRODUCT IMAGES (ONLY PATH)
        // =========================
        if ($product->images) {
            $product->images->transform(function ($img) {
                return [
                    'id' => $img->id,
                    'product_id' => $img->product_id,
                    'variant_id' => $img->variant_id,
                    'image_url' => $img->image_url, // ✅ RAW PATH ONLY
                    'is_primary' => $img->is_primary,
                    'sort_order' => $img->sort_order,
                ];
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
    // IMAGES (RAW PATH ONLY)
    // =========================
    public function images($id)
    {
        return response()->json([
            'success' => true,
            'data' => \App\Models\ProductImage::where('product_id', $id)
                ->orderBy('sort_order')
                ->get()
                ->map(function ($img) {
                    return [
                        'id' => $img->id,
                        'product_id' => $img->product_id,
                        'variant_id' => $img->variant_id,
                        'image_url' => $img->image_url, // ✅ RAW PATH ONLY
                        'is_primary' => $img->is_primary,
                        'sort_order' => $img->sort_order,
                    ];
                })
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
                ->with('user')
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