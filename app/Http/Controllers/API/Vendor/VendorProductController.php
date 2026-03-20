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

class VendorProductController extends Controller
{

    // POST /vendor/products
    public function store(Request $request)
    {
        $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0'
        ]);

        $slug = Str::slug($request->name);

        // ensure slug unique
        $count = Product::where('slug','LIKE',$slug.'%')->count();
        if($count > 0){
            $slug = $slug.'-'.($count+1);
        }

        $product = Product::create([
            'vendor_id' => $request->vendor_id,
            'category_id' => $request->category_id,
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'price' => $request->price,
            'stock' => $request->stock,
            'status' => true,
            'is_approved' => false
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product
        ],201);
    }

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

    // ✅ Admin bypass (adjust if needed)
    if (!isset($user->is_admin) || !$user->is_admin) {
        if ($product->vendor_id !== $user->vendor_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this product'
            ], 403);
        }
    }

    // ✅ Delete product images (IMPORTANT)
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

    // ✅ Delete variant images (optional but recommended)
    $variants = ProductVariant::where('product_id', $product->id)->get();

    foreach ($variants as $variant) {
        if ($variant->image) {
            $path = str_replace(url('/storage/'), 'public/', $variant->image);

            if (Storage::exists($path)) {
                Storage::delete($path);
            }
        }
    }

    // ✅ Delete product (cascade handles rest)
    $product->delete();

    return response()->json([
        'success' => true,
        'message' => 'Product deleted successfully'
    ]);
}


public function updateStock(Request $request, $id)
{
    $product = Product::find($id);

    if (!$product) {
        return response()->json([
            'success' => false,
            'message' => 'Product not found'
        ], 404);
    }

    $user = Auth::user();

    // ✅ Ownership check
    if (!isset($user->is_admin) || !$user->is_admin) {
        if ($product->vendor_id !== $user->vendor_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }
    }

    $request->validate([
        'stock' => 'required|integer|min:0',
        'variant_id' => 'nullable|exists:product_variants,id'
    ]);

    // ✅ If variant_id provided → update variant stock
    if ($request->has('variant_id')) {

        $variant = ProductVariant::where('id', $request->variant_id)
            ->where('product_id', $product->id)
            ->first();

        if (!$variant) {
            return response()->json([
                'success' => false,
                'message' => 'Variant not found for this product'
            ], 404);
        }

        $variant->update([
            'stock' => $request->stock
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Variant stock updated successfully',
            'data' => $variant
        ]);
    }

    // ✅ Otherwise update product stock
    $product->update([
        'stock' => $request->stock
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Product stock updated successfully',
        'data' => $product
    ]);
}

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

    // ✅ Ownership check (admin bypass optional)
    if (!isset($user->is_admin) || !$user->is_admin) {
        if ($product->vendor_id !== $user->vendor_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }
    }

    // ✅ Validation (partial updates allowed)
    $request->validate([
        'name' => 'sometimes|string|max:255',
        'category_id' => 'sometimes|exists:categories,id',
        'description' => 'nullable|string',
        'price' => 'sometimes|numeric|min:0',
        'stock' => 'sometimes|integer|min:0',
        'status' => 'sometimes|boolean'
    ]);

    $data = $request->only([
        'name',
        'category_id',
        'description',
        'price',
        'stock',
        'status'
    ]);

    // ✅ Handle slug if name updated
    if ($request->has('name')) {

        $slug = Str::slug($request->name);

        $count = Product::where('slug', 'LIKE', $slug . '%')
            ->where('id', '!=', $id)
            ->count();

        if ($count > 0) {
            $slug = $slug . '-' . ($count + 1);
        }

        $data['slug'] = $slug;
    }

    $product->update($data);

    return response()->json([
        'success' => true,
        'message' => 'Product updated successfully',
        'data' => $product
    ]);
}

}