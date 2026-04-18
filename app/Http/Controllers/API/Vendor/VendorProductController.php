<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Vendor;
use App\Models\User;

class VendorProductController extends Controller
{
    // ✅ Helper: get creator id (vendor or user)
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

    // ✅ STORE PRODUCT
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
            'is_featured' => $request->is_featured ?? 0,
            'status' => 0,
            'approval_status' => 'pending',
        ]);

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

    // ✅ GET PRODUCT - FIXED with all missing fields
    public function getProductById($id)
    {
        $product = Product::with([
            'category',
            'vendor',
            'brand',  // ✅ Added brand relationship
            'images',
            'variants.attributeValues.attribute',
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
                'short_description' => $product->short_description,  // ✅ ADDED
                'description' => $product->description,
                'price' => $product->price,
                'discount_price' => $product->discount_price,  // ✅ ADDED
                'cost_price' => $product->cost_price,  // ✅ ADDED
                'stock' => $product->stock,
                'weight' => $product->weight,  // ✅ ADDED
                'length' => $product->length,  // ✅ ADDED
                'width' => $product->width,    // ✅ ADDED
                'height' => $product->height,  // ✅ ADDED
                'is_featured' => (bool)$product->is_featured,  // ✅ ADDED
                'status' => $product->status,
                'approval_status' => $product->approval_status,
                'brand_id' => $product->brand_id,  // ✅ ADDED
                'category_id' => $product->category_id,  // ✅ ADDED
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,

                'creator' => $creator,

                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                    'slug' => $product->category->slug ?? null,
                    'parent_id' => $product->category->parent_id  // ✅ ADDED parent_id
                ] : null,

                'brand' => $product->brand ? [  // ✅ ADDED brand details
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
                        'image' => $variant->image,
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

    // ✅ UPDATE PRODUCT
    public function updateProduct(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|exists:categories,id',
            'brand_id' => 'sometimes|exists:brands,id',
            'short_description' => 'nullable|string',  // ✅ ADDED validation
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'weight' => 'nullable|numeric',
            'length' => 'nullable|numeric',
            'width' => 'nullable|numeric',
            'height' => 'nullable|numeric',
            'is_featured' => 'sometimes|boolean',
            'specifications' => 'nullable|array',
            'specifications.*.name' => 'required_with:specifications|string|max:255',
            'specifications.*.value' => 'required_with:specifications|string',
        ]);

        $data = $request->only([
            'category_id',
            'brand_id',
            'short_description',  // ✅ ADDED
            'description',
            'price',
            'discount_price',
            'cost_price',
            'stock',
            'weight',
            'length',
            'width',
            'height',
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

    // ✅ UPDATE STOCK
    public function updateStock(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

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

    // ✅ DELETE PRODUCT
    public function deleteProduct($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        $images = ProductImage::where('product_id', $product->id)->get();

        foreach ($images as $image) {
            if ($image->image_url) {
                $path = str_replace(url('/storage/'), 'public/', $image->image_url);
                if (Storage::exists($path)) {
                    Storage::delete($path);
                }
            }
            $image->delete();
        }

        ProductVariant::where('product_id', $product->id)->delete();
        $product->specifications()->delete();  // ✅ Also delete specifications
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }
}