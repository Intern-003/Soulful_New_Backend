<?php
// app/Http/Controllers/API/Vendor/VendorProductController.php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Vendor;
use App\Services\ImageUploadService;

class VendorProductController extends Controller
{
    /**
     * Helper: get creator id (vendor or user)
     */
    private function getCreator($user)
    {
        if ($user->vendor) {
            return [
                'vendor_id' => $user->vendor->id,
                'user_id' => null
            ];
        }

        return [
            'vendor_id' => null,
            'user_id' => $user->id
        ];
    }

    /**
     * Vendor product query with proper permissions
     */
    private function vendorProductQuery($user)
    {
        // ADMIN bypass
        if ($user->role_id === 1) {
            return Product::query();
        }

        return Product::where(function ($q) use ($user) {
            $q->where('vendor_id', optional($user->vendor)->id)
              ->orWhere('user_id', $user->id);
        });
    }
/**
 * GET /vendor/products - List vendor products with filters
 */
public function index(Request $request)
{
    $user = Auth::user();
    $query = $this->vendorProductQuery($user);

    // Apply filters
    if ($request->has('search')) {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->where('name', 'LIKE', "%{$search}%")
              ->orWhere('description', 'LIKE', "%{$search}%")
              ->orWhere('sku', 'LIKE', "%{$search}%");
        });
    }

    if ($request->has('category_id')) {
        $query->where('category_id', $request->category_id);
    }

    if ($request->has('status')) {
        $query->where('status', $request->status);
    }

    if ($request->has('approval_status')) {
        $query->where('approval_status', $request->approval_status);
    }

    if ($request->has('stock')) {
        if ($request->stock === 'in_stock') {
            $query->where('stock', '>', 0);
        } elseif ($request->stock === 'out_of_stock') {
            $query->where('stock', '=', 0);
        } elseif ($request->stock === 'low_stock') {
            $query->where('stock', '>', 0)->where('stock', '<=', 10);
        }
    }

    // Sorting
    $sortBy = $request->sort_by ?? 'latest';
    switch ($sortBy) {
        case 'price_low':
            $query->orderBy('price', 'asc');
            break;
        case 'price_high':
            $query->orderBy('price', 'desc');
            break;
        case 'name_asc':
            $query->orderBy('name', 'asc');
            break;
        case 'name_desc':
            $query->orderBy('name', 'desc');
            break;
        case 'oldest':
            $query->oldest();
            break;
        default:
            $query->latest();
            break;
    }

    // Pagination
    $perPage = $request->per_page ?? 15;
    $products = $query->with([
        'category:id,name',
        'brand:id,name',
        'images' => function($q) {
            $q->where('is_primary', 1)->orWhere('sort_order', 0);
        }
    ])->paginate($perPage);

    // Transform products for response
    $products->getCollection()->transform(function ($product) {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $product->price,
            'discount_price' => $product->discount_price,
            'current_price' => $product->discount_price ?? $product->price,
            'stock' => $product->stock,
            'status' => $product->status,
            'approval_status' => $product->approval_status,
            'category' => $product->category ? $product->category->name : null,
            'brand' => $product->brand ? $product->brand->name : null,
            'primary_image' => $product->images->first()->image_url ?? null,
            'created_at' => $product->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $product->updated_at->format('Y-m-d H:i:s')
        ];
    });

    return response()->json([
        'success' => true,
        'data' => $products
    ]);
}
    /**
     * STORE PRODUCT - Enhanced with tax & shipping fields
     */
    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'name' => 'required|string|max:255',
            'short_description' => 'nullable|string',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'weight' => 'nullable|numeric',
            'length' => 'nullable|numeric',
            'width' => 'nullable|numeric',
            'height' => 'nullable|numeric',
            'tax_rate' => 'nullable|numeric|min:0|max:100', // NEW
            'shipping_mode' => 'nullable|in:vendor,marketplace', // NEW
            'shipping_charge' => 'nullable|numeric|min:0', // NEW
            'specifications' => 'nullable|array',
            'specifications.*.name' => 'required_with:specifications|string|max:255',
            'specifications.*.value' => 'required_with:specifications|string',
        ]);

        $user = Auth::user();
        $creator = $this->getCreator($user);

        $slug = Str::slug($request->name);
        $count = Product::where('slug', 'LIKE', $slug . '%')->count();
        if ($count > 0) {
            $slug = $slug . '-' . ($count + 1);
        }

        $product = Product::create([
            'vendor_id' => $creator['vendor_id'],
            'user_id' => $creator['user_id'],
            'category_id' => $request->category_id,
            'brand_id' => $request->brand_id,
            'name' => $request->name,
            'slug' => $slug,
            'short_description' => $request->short_description,
            'description' => $request->description,
            'price' => $request->price,
            'discount_price' => $request->discount_price,
            'cost_price' => $request->cost_price,
            'stock' => $request->stock,
            'weight' => $request->weight,
            'length' => $request->length,
            'width' => $request->width,
            'height' => $request->height,
            'tax_rate' => $request->tax_rate ?? 18, // NEW - default 18% GST
            'shipping_mode' => $request->shipping_mode ?? 'vendor', // NEW
            'shipping_charge' => $request->shipping_charge, // NEW
            'is_featured' => $request->is_featured ?? 0,
            'status' => 0,
            'approval_status' => 'pending',
        ]);

        // Save specifications
        if ($request->has('specifications') && is_array($request->specifications)) {
            foreach ($request->specifications as $spec) {
                if (!empty($spec['name']) && !empty($spec['value'])) {
                    $product->specifications()->create([
                        'name' => $spec['name'],
                        'value' => $spec['value'],
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product->load('specifications')
        ], 201);
    }

    /**
     * GET PRODUCT - Enhanced with all fields
     */
    public function getProductById($id)
    {
        $product = $this->vendorProductQuery(auth()->user())
            ->with([
                'category',
                'vendor',
                //'vendor.settings',
                'brand',
                'images',
                'variants.attributeValues.attribute',
                'variants.images',
                'specifications'
            ])->findOrFail($id);

        if ($product->vendor_id) {
            $creator = [
                'type' => 'vendor',
                'id' => $product->vendor->id,
                'name' => $product->vendor->store_name,
                'email' => $product->vendor->user->email ?? null
            ];
        } else {
            $creator = [
                'type' => 'user',
                'id' => $product->user->id,
                'name' => $product->user->name,
                'email' => $product->user->email
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Product retrieved successfully',
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'short_description' => $product->short_description,
                'description' => $product->description,
                'price' => $product->price,
                'discount_price' => $product->discount_price,
                'cost_price' => $product->cost_price,
                'stock' => $product->stock,
                'weight' => $product->weight,
                'length' => $product->length,
                'width' => $product->width,
                'height' => $product->height,
                'tax_rate' => $product->tax_rate, // NEW
                'shipping_mode' => $product->shipping_mode, // NEW
                'shipping_charge' => $product->shipping_charge, // NEW
                'is_featured' => (bool) $product->is_featured,
                'status' => $product->status,
                'approval_status' => $product->approval_status,
                'brand_id' => $product->brand_id,
                'category_id' => $product->category_id,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
                'creator' => $creator,
                // 'vendor_settings' => $product->vendor ? [
                //     'shipping_mode' => $product->vendor->settings->shipping_mode ?? 'flat_rate',
                //     'flat_shipping_rate' => $product->vendor->settings->flat_shipping_rate ?? 0,
                //     'tax_calculation' => $product->vendor->settings->tax_calculation ?? 'cgst_sgst',
                // ] : null,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                    'slug' => $product->category->slug ?? null,
                    'parent_id' => $product->category->parent_id
                ] : null,
                'brand' => $product->brand ? [
                    'id' => $product->brand->id,
                    'name' => $product->brand->name
                ] : null,
                'images' => $product->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'image_url' => $image->image_url,
                        'is_primary' => $image->is_primary ?? false,
                        'position' => $image->position ?? null
                    ];
                }),
                'variants' => $product->variants->map(function ($variant) {
                    return [
                        'id' => $variant->id,
                        'sku' => $variant->sku,
                        'price' => $variant->price,
                        'stock' => $variant->stock,
                        'weight' => $variant->weight,
                        'barcode' => $variant->barcode,
                        'discount_price' => $variant->discount_price,
                        'tax_rate' => $variant->tax_rate, // NEW
                        'shipping_charge' => $variant->shipping_charge, // NEW
                        'cost_price' => $variant->cost_price, // NEW
                        'image' => $variant->image,
                        'images' => $variant->images->map(function ($image) {
                            return [
                                'id' => $image->id,
                                'image_url' => $image->image_url,
                                'is_primary' => $image->is_primary,
                            ];
                        }),
                        'attribute_value_ids' => $variant->attributeValues->pluck('id')->toArray(),
                        'attributes' => $variant->attributeValues->map(function ($val) {
                            return [
                                'attribute_id' => $val->attribute_id,
                                'attribute_name' => $val->attribute->name,
                                'value_id' => $val->id,
                                'value' => $val->value,
                            ];
                        })
                    ];
                }),
                'specifications' => $product->specifications->map(function ($spec) {
                    return [
                        'id' => $spec->id,
                        'name' => $spec->name,
                        'value' => $spec->value,
                        'created_at' => $spec->created_at,
                        'updated_at' => $spec->updated_at,
                    ];
                })
            ]
        ]);
    }

    /**
     * UPDATE PRODUCT - Enhanced with tax & shipping
     */
    public function updateProduct(Request $request, $id)
    {
        $product = $this->vendorProductQuery(auth()->user())->findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'short_description' => 'nullable|string',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'weight' => 'nullable|numeric',
            'length' => 'nullable|numeric',
            'width' => 'nullable|numeric',
            'height' => 'nullable|numeric',
            'tax_rate' => 'nullable|numeric|min:0|max:100', // NEW
            'shipping_mode' => 'nullable|in:vendor,marketplace', // NEW
            'shipping_charge' => 'nullable|numeric|min:0', // NEW
            'is_featured' => 'sometimes|boolean',
            'specifications' => 'nullable|array',
            'specifications.*.name' => 'required_with:specifications|string|max:255',
            'specifications.*.value' => 'required_with:specifications|string',
        ]);

        $data = $request->only([
            'category_id',
            'brand_id',
            'short_description',
            'description',
            'price',
            'discount_price',
            'cost_price',
            'stock',
            'weight',
            'length',
            'width',
            'height',
            'tax_rate', // NEW
            'shipping_mode', // NEW
            'shipping_charge', // NEW
            'is_featured'
        ]);

        if ($request->has('name')) {
            $slug = Str::slug($request->name);
            $count = Product::where('slug', 'LIKE', $slug . '%')
                ->where('id', '!=', $id)
                ->count();

            if ($count > 0) {
                $slug = $slug . '-' . ($count + 1);
            }

            $data['name'] = $request->name;
            $data['slug'] = $slug;
        }

        $product->update($data);

        if ($request->has('specifications')) {
            $product->specifications()->delete();

            if (is_array($request->specifications)) {
                foreach ($request->specifications as $spec) {
                    if (!empty($spec['name']) && !empty($spec['value'])) {
                        $product->specifications()->create([
                            'name' => $spec['name'],
                            'value' => $spec['value'],
                        ]);
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $product->load('specifications')
        ]);
    }

    /**
     * UPDATE STOCK
     */
    public function updateStock(Request $request, $id)
    {
        $product = $this->vendorProductQuery(auth()->user())->findOrFail($id);

        $request->validate([
            'stock' => 'required|integer|min:0',
            'variant_id' => 'nullable|exists:product_variants,id'
        ]);

        if ($request->has('variant_id')) {
            $variant = ProductVariant::where('id', $request->variant_id)
                ->where('product_id', $product->id)
                ->first();

            if (!$variant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Variant not found'
                ], 404);
            }

            $variant->update(['stock' => $request->stock]);

            return response()->json([
                'success' => true,
                'message' => 'Variant stock updated successfully',
                'data' => $variant
            ]);
        }

        $product->update(['stock' => $request->stock]);

        return response()->json([
            'success' => true,
            'message' => 'Product stock updated successfully',
            'data' => $product
        ]);
    }

    /**
     * DELETE PRODUCT
     */
    public function deleteProduct($id)
    {
        $product = $this->vendorProductQuery(auth()->user())->findOrFail($id);

        $images = ProductImage::where('product_id', $product->id)->get();

        foreach ($images as $image) {
            if ($image->image_url) {
                ImageUploadService::deleteImage($image->image_url);
            }
            $image->delete();
        }

        ProductVariant::where('product_id', $product->id)->delete();
        $product->specifications()->delete();
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }
}