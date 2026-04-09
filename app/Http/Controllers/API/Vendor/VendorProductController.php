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
        //dd("hello");
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
        ]);

        $user = Auth::user();

        // $creatorId = $this->getCreatorId($user);


        $user = Auth::user();

        $creator = $this->getCreator($user);

        // slug
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
            'is_approved' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product
        ], 201);


    }

    // ✅ GET PRODUCT
    public function getProductById($id)
    {

        $product = Product::with([
            'category',
            'vendor',
            'images',
            //'variants.attributes',
            'variants.attributeValues.attribute',
            //'variants.attributeValues'
        ])->findOrFail($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }
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
                'description' => $product->description,
                'price' => $product->price,
                'stock' => $product->stock,
                'status' => $product->status,
                'is_approved' => $product->is_approved,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,

                // ✅ creator (vendor OR user)
                'creator' => $creator,

                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                    'slug' => $product->category->slug ?? null
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

                        // ✅ CLEAN STRUCTURE
                        'attributes' => $variant->attributeValues->map(function ($val) {
                            return [
                                'attribute_id' => $val->attribute_id,
                                'attribute_name' => $val->attribute->name,
                                'value_id' => $val->id,
                                'value' => $val->value,
                            ];
                        })
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
        $user = Auth::user();

        if ($product->vendor_id) {
            // product belongs to vendor
            if (!$user->vendor || $user->vendor->id !== $product->vendor_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
        } else {
            // product belongs to normal user
            if ($product->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|exists:categories,id',
            'brand_id' => 'sometimes|exists:brands,id',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'weight' => 'nullable|numeric',
            'length' => 'nullable|numeric',
            'width' => 'nullable|numeric',
            'height' => 'nullable|numeric',
            // 'status' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
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
            'is_featured'
        ]);

        // slug update
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

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $product
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
        $user = Auth::user();

        if ($product->vendor_id) {
            // product belongs to vendor
            if (!$user->vendor || $user->vendor->id !== $product->vendor_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
        } else {
            // product belongs to normal user
            if ($product->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
        }

        // delete images
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

        // delete variants
        ProductVariant::where('product_id', $product->id)->delete();

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }
}