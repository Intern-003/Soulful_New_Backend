<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\Category;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    // =========================
    // BASE QUERY (With Ratings)
    // =========================
    private function baseProductQuery()
    {
        return Product::select('products.*')
            ->selectRaw('COALESCE((
                SELECT AVG(rating)
                FROM reviews
                WHERE reviews.product_id = products.id
                AND reviews.status = 1
            ), 0) as average_rating')
            ->selectRaw('COALESCE((
                SELECT COUNT(*)
                FROM reviews
                WHERE reviews.product_id = products.id
                AND reviews.status = 1
            ), 0) as total_reviews')
            ->with([
                'category',
                'brand',
                'images' => function($q) {
                    $q->orderBy('is_primary', 'desc')->orderBy('sort_order');
                },
                'variants' => function($q) {
                    $q->with(['attributeValues.attribute', 'images']);
                },
                'specifications'
            ])
            ->where('status', 1)
            ->where('approval_status', 'approved');
    }

    // =========================
    // SAFE QUERY (Without extra selects)
    // =========================
    private function safeProductQuery()
    {
        return Product::with([
            'category',
            'brand',
            'images' => function($q) {
                $q->orderBy('is_primary', 'desc')->orderBy('sort_order');
            },
            'variants' => function($q) {
                $q->with(['attributeValues.attribute', 'images']);
            },
            'specifications',
            'vendor:id,store_name,store_slug,store_logo,rating',
            'user:id,name,email'
        ])
        ->where('status', 1)
        ->where('approval_status', 'approved');
    }

    // =========================
    // INDEX / LIST PRODUCTS
    // =========================
    public function index(Request $request)
    {
        $query = $this->baseProductQuery();

        // Category filter
        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }
        
        // Category slug filter
        if ($request->category_slug) {
            $category = Category::where('slug', $request->category_slug)->first();
            if ($category) {
                $query->where('category_id', $category->id);
            }
        }

        // Brand filter
        if ($request->brand_id) {
            $query->where('brand_id', $request->brand_id);
        }

        // Vendor filter
        if ($request->vendor_id) {
            $query->where('vendor_id', $request->vendor_id);
        }

        // Price range
        if ($request->price_min) {
            // Use discounted price if available
            $query->where(function($q) use ($request) {
                $q->where('price', '>=', $request->price_min)
                  ->orWhere('discount_price', '>=', $request->price_min);
            });
        }
        if ($request->price_max) {
            $query->where(function($q) use ($request) {
                $q->where('price', '<=', $request->price_max)
                  ->orWhere('discount_price', '<=', $request->price_max);
            });
        }

        // Rating filter
        if ($request->min_rating) {
            $query->having('average_rating', '>=', $request->min_rating);
        }

        // Stock filter (in stock only)
        if ($request->in_stock) {
            $query->where('stock', '>', 0);
        }

        // On sale filter (has discount)
        if ($request->on_sale) {
            $query->whereNotNull('discount_price');
        }

        // Featured products
        if ($request->featured) {
            $query->where('is_featured', 1);
        }

        // Search
        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('short_description', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->sort_by ?? 'latest';
        switch ($sortBy) {
            case 'price_low':
                $query->orderBy(DB::raw('COALESCE(discount_price, price)'), 'asc');
                break;
            case 'price_high':
                $query->orderBy(DB::raw('COALESCE(discount_price, price)'), 'desc');
                break;
            case 'popular':
                $query->orderBy('sold_count', 'desc');
                break;
            case 'rating':
                $query->orderBy('average_rating', 'desc');
                break;
            case 'oldest':
                $query->oldest();
                break;
            default:
                $query->latest();
                break;
        }

        $perPage = $request->per_page ?? 12;
        $products = $query->paginate($perPage);

        // Transform variants for each product
        $products->getCollection()->transform(function ($product) {
            return $this->transformProduct($product);
        });

        // Get filter counts for sidebar
        $filterCounts = [];
        if ($request->get_filters) {
            $filterCounts = $this->getFilterCounts();
        }

        return response()->json([
            'success' => true,
            'data' => $products,
            'filters' => $filterCounts,
            'sort_options' => [
                'latest' => 'Newest First',
                'price_low' => 'Price: Low to High',
                'price_high' => 'Price: High to Low',
                'popular' => 'Most Popular',
                'rating' => 'Top Rated'
            ]
        ]);
    }

    // =========================
    // SHOW SINGLE PRODUCT
    // =========================
    public function show($identifier)
    {
        $product = $this->safeProductQuery()
            ->with([
                'reviews' => function($q) {
                    $q->where('status', 1)
                      ->with('user:id,name')
                      ->latest()
                      ->limit(10);
                },
                'vendor:id,store_name,store_slug,store_logo,description,rating,commission_rate,status',
                'user:id,name,email',
                'category.parent'
            ])
            ->where(function ($q) use ($identifier) {
                $q->where('slug', $identifier)
                  ->orWhere('id', $identifier);
            })
            ->firstOrFail();

        // Calculate ratings
        $product->average_rating = $product->reviews->avg('rating') ?? 0;
        $product->total_reviews = $product->reviews->count();
        
        // Rating distribution
        $product->rating_distribution = [
            5 => $product->reviews->where('rating', 5)->count(),
            4 => $product->reviews->where('rating', 4)->count(),
            3 => $product->reviews->where('rating', 3)->count(),
            2 => $product->reviews->where('rating', 2)->count(),
            1 => $product->reviews->where('rating', 1)->count(),
        ];

        // Get current selling price
        $product->current_price = $product->discount_price ?? $product->price;
        $product->discount_percentage = $product->price > 0 && $product->discount_price 
            ? round((($product->price - $product->discount_price) / $product->price) * 100)
            : 0;

        // Creator info
        if ($product->vendor_id && $product->vendor) {
            $product->creator = [
                'type' => 'vendor',
                'id' => $product->vendor->id,
                'name' => $product->vendor->store_name,
                'slug' => $product->vendor->store_slug,
                'logo' => $product->vendor->store_logo,
                'rating' => $product->vendor->rating,
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

        // Transform variants
        $product = $this->transformProduct($product);

        // Get related products
        $product->related_products = $this->getRelatedProducts($product);

       

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    // =========================
    // TRANSFORM PRODUCT (Format response)
    // =========================
    private function transformProduct($product)
    {
        // Transform variants
        if ($product->variants) {
            $product->variants->transform(function ($variant) {
                // Format attributes
                $attributes = [];
                foreach ($variant->attributeValues as $value) {
                    $attrName = $value->attribute->name ?? 'Attribute';
                    $attributes[$attrName] = [
                        'value' => $value->value,
                        'hex' => $value->hex_code,
                        'attribute_id' => $value->attribute_id,
                        'value_id' => $value->id
                    ];
                }
                unset($variant->attributeValues);
                $variant->attributes = $attributes;

                // Format images
                $variant->images->transform(function ($img) {
                    return [
                        'id' => $img->id,
                        'image_url' => $img->image_url,
                        'is_primary' => (bool) $img->is_primary,
                    ];
                });

                // Primary image
                $variant->primary_image = optional(
                    $variant->images->firstWhere('is_primary', true)
                )['image_url'] ?? optional($variant->images->first())['image_url'];

                // Add variant price info
                $variant->current_price = $variant->discount_price ?? $variant->price;
                $variant->is_in_stock = $variant->stock > 0;

                return $variant;
            });
        }

        // Transform product images
        if ($product->images) {
            $product->images->transform(function ($img) {
                return [
                    'id' => $img->id,
                    'image_url' => $img->image_url,
                    'is_primary' => (bool) $img->is_primary,
                    'sort_order' => $img->sort_order
                ];
            });
        }

        // Product primary image
        $product->primary_image = optional(
            $product->images->firstWhere('is_primary', true)
        )['image_url'] ?? optional($product->images->first())['image_url'];

        // Stock status
        $product->stock_status = $product->stock > 0 ? 'in_stock' : 'out_of_stock';
        $product->stock_text = $product->stock > 0 
            ? ($product->stock <= 5 ? 'Only ' . $product->stock . ' left' : 'In Stock')
            : 'Out of Stock';

        // Is on sale
        $product->is_on_sale = !is_null($product->discount_price) && $product->discount_price < $product->price;

        return $product;
    }

    // =========================
    // GET RELATED PRODUCTS
    // =========================
    private function getRelatedProducts($product, $limit = 8)
    {
        return $this->baseProductQuery()
            ->where(function($q) use ($product) {
                $q->where('category_id', $product->category_id)
                  ->orWhere('brand_id', $product->brand_id);
            })
            ->where('id', '!=', $product->id)
            ->take($limit)
            ->get()
            ->map(function($related) {
                $related->current_price = $related->discount_price ?? $related->price;
                $related->primary_image = optional($related->images->firstWhere('is_primary', true))['image_url'] 
                    ?? optional($related->images->first())['image_url'];
                return $related;
            });
    }

    // =========================
    // GET FILTER COUNTS
    // =========================
    private function getFilterCounts()
    {
        $baseQuery = Product::where('status', 1)->where('approval_status', 'approved');
        
        return [
            'categories' => Category::withCount(['products' => function($q) {
                $q->where('status', 1)->where('approval_status', 'approved');
            }])->having('products_count', '>', 0)->get(['id', 'name', 'slug']),
            
            'brands' => Brand::withCount(['products' => function($q) {
                $q->where('status', 1)->where('approval_status', 'approved');
            }])->having('products_count', '>', 0)->get(['id', 'name']),
            
            'price_range' => [
                'min' => $baseQuery->min(DB::raw('COALESCE(discount_price, price)')),
                'max' => $baseQuery->max(DB::raw('COALESCE(discount_price, price)'))
            ]
        ];
    }

    // =========================
    // SEARCH PRODUCTS
    // =========================
    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2'
        ]);

        $products = $this->baseProductQuery()
            ->where('name', 'LIKE', "%{$request->q}%")
            ->paginate(20);

        $products->getCollection()->transform(function ($product) {
            $product->current_price = $product->discount_price ?? $product->price;
            $product->primary_image = optional($product->images->firstWhere('is_primary', true))['image_url'] 
                ?? optional($product->images->first())['image_url'];
            return $product;
        });

        return response()->json([
            'success' => true,
            'data' => $products,
            'query' => $request->q
        ]);
    }

    // =========================
    // FEATURED PRODUCTS
    // =========================
    public function featured(Request $request)
    {
        $limit = $request->get('limit', 10);
        
        $products = $this->baseProductQuery()
            ->where('is_featured', 1)
            ->take($limit)
            ->get()
            ->map(function($product) {
                $product->current_price = $product->discount_price ?? $product->price;
                $product->primary_image = optional($product->images->firstWhere('is_primary', true))['image_url'] 
                    ?? optional($product->images->first())['image_url'];
                return $product;
            });

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    // =========================
    // LATEST PRODUCTS
    // =========================
    public function latest(Request $request)
    {
        $limit = $request->get('limit', 10);
        
        $products = $this->baseProductQuery()
            ->latest()
            ->take($limit)
            ->get()
            ->map(function($product) {
                $product->current_price = $product->discount_price ?? $product->price;
                $product->primary_image = optional($product->images->firstWhere('is_primary', true))['image_url'] 
                    ?? optional($product->images->first())['image_url'];
                return $product;
            });

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    // =========================
    // DEALS / DISCOUNTED PRODUCTS
    // =========================
    public function deals(Request $request)
    {
        $limit = $request->get('limit', 20);
        
        $products = $this->baseProductQuery()
            ->whereNotNull('discount_price')
            ->whereRaw('discount_price < price')
            ->orderBy(DB::raw('((price - discount_price) / price) * 100'), 'desc')
            ->take($limit)
            ->get()
            ->map(function($product) {
                $product->current_price = $product->discount_price;
                $product->discount_percentage = round((($product->price - $product->discount_price) / $product->price) * 100);
                $product->primary_image = optional($product->images->firstWhere('is_primary', true))['image_url'] 
                    ?? optional($product->images->first())['image_url'];
                return $product;
            });

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

  // =========================
// BEST SELLERS (by order items quantity) - FIXED
// =========================
public function bestSellers(Request $request)
{
    $limit = $request->get('limit', 10);
    
    // Get best selling product IDs from completed orders
    $bestSellingProductIds = \App\Models\OrderItem::select('product_id', DB::raw('SUM(quantity) as total_sold'))
        ->whereHas('order', function($q) {
            $q->where('payment_status', 'paid');
        })
        ->groupBy('product_id')
        ->orderByDesc('total_sold')
        ->limit($limit)
        ->pluck('product_id')
        ->toArray();
    
    // If no best sellers found, return featured or latest products
    if (empty($bestSellingProductIds)) {
        $products = $this->baseProductQuery()
            ->latest()
            ->take($limit)
            ->get()
            ->map(function($product) {
                $product->current_price = $product->discount_price ?? $product->price;
                $product->primary_image = optional($product->images->firstWhere('is_primary', true))['image_url'] 
                    ?? optional($product->images->first())['image_url'];
                return $product;
            });
            
        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }
    
    // Get products in the order of best selling IDs
    $products = $this->baseProductQuery()
        ->whereIn('id', $bestSellingProductIds)
        ->get()
        ->sortBy(function($product) use ($bestSellingProductIds) {
            return array_search($product->id, $bestSellingProductIds);
        })
        ->values()
        ->map(function($product) {
            $product->current_price = $product->discount_price ?? $product->price;
            $product->primary_image = optional($product->images->firstWhere('is_primary', true))['image_url'] 
                ?? optional($product->images->first())['image_url'];
            return $product;
        });

    return response()->json([
        'success' => true,
        'data' => $products
    ]);
}

    // =========================
    // RELATED PRODUCTS (by ID)
    // =========================
    public function related($id, Request $request)
    {
        $product = Product::findOrFail($id);
        $limit = $request->get('limit', 8);
        
        $related = $this->getRelatedProducts($product, $limit);

        return response()->json([
            'success' => true,
            'data' => $related
        ]);
    }

    // =========================
    // BULK RELATED PRODUCTS (for multiple products)
    // =========================
    public function relatedBulk(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id'
        ]);

        $relatedProducts = [];
        foreach ($request->product_ids as $id) {
            $product = Product::find($id);
            if ($product) {
                $relatedProducts[$id] = $this->getRelatedProducts($product, 4);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $relatedProducts
        ]);
    }

    // =========================
    // PRODUCT IMAGES
    // =========================
    public function images($id)
    {
        $product = Product::findOrFail($id);
        
        $images = $product->images()
            ->orderBy('sort_order')
            ->get()
            ->map(function ($img) {
                return [
                    'id' => $img->id,
                    'product_id' => $img->product_id,
                    'variant_id' => $img->variant_id,
                    'image_url' => $img->image_url,
                    'is_primary' => (bool) $img->is_primary,
                    'sort_order' => $img->sort_order,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $images
        ]);
    }

    // =========================
    // PRODUCT REVIEWS
    // =========================
    public function reviews($id, Request $request)
    {
        $product = Product::findOrFail($id);
        
        $query = Review::where('product_id', $id)
            ->where('status', 1)
            ->with('user:id,name,avatar');
        
        // Filter by rating
        if ($request->rating) {
            $query->where('rating', $request->rating);
        }
        
        // Sort
        $sort = $request->sort ?? 'latest';
        switch ($sort) {
            case 'oldest':
                $query->oldest();
                break;
            case 'highest':
                $query->orderBy('rating', 'desc');
                break;
            case 'lowest':
                $query->orderBy('rating', 'asc');
                break;
            default:
                $query->latest();
                break;
        }
        
        $reviews = $query->paginate(10);
        
        // Add rating summary
        $ratingSummary = [
            'average' => round($product->reviews()->where('status', 1)->avg('rating') ?? 0, 1),
            'total' => $product->reviews()->where('status', 1)->count(),
            'distribution' => [
                5 => $product->reviews()->where('status', 1)->where('rating', 5)->count(),
                4 => $product->reviews()->where('status', 1)->where('rating', 4)->count(),
                3 => $product->reviews()->where('status', 1)->where('rating', 3)->count(),
                2 => $product->reviews()->where('status', 1)->where('rating', 2)->count(),
                1 => $product->reviews()->where('status', 1)->where('rating', 1)->count(),
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $reviews,
            'rating_summary' => $ratingSummary
        ]);
    }

    // =========================
    // PRODUCT RATING ONLY
    // =========================
    public function rating($id)
    {
        $product = Product::findOrFail($id);
        
        $average = round(
            Review::where('product_id', $id)
                ->where('status', 1)
                ->avg('rating'),
            1
        );
        
        $total = Review::where('product_id', $id)
            ->where('status', 1)
            ->count();

        return response()->json([
            'success' => true,
            'average_rating' => $average,
            'total_reviews' => $total
        ]);
    }

    // =========================
    // PRODUCT BY SLUG (Alias for show)
    // =========================
    public function bySlug($slug)
    {
        return $this->show($slug);
    }

    // =========================
    // QUICK VIEW (Minimal product data)
    // =========================
    public function quickView($id)
    {
        $product = Product::with([
            'images' => function($q) {
                $q->orderBy('is_primary', 'desc');
            },
            'variants' => function($q) {
                $q->with('attributeValues.attribute');
            },
            'vendor:id,store_name'
        ])
        ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => $product->price,
                'discount_price' => $product->discount_price,
                'current_price' => $product->discount_price ?? $product->price,
                'stock' => $product->stock,
                'short_description' => $product->short_description,
                'images' => $product->images->map(fn($img) => $img->image_url),
                'variants' => $product->variants,
                'vendor_name' => $product->vendor->store_name ?? null,
                'average_rating' => round($product->reviews()->avg('rating') ?? 0, 1),
                'total_reviews' => $product->reviews()->count()
            ]
        ]);
    }
}