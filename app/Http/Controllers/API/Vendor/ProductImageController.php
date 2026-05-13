<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductImage;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Services\ImageUploadService;

class ProductImageController extends Controller
{

    // POST /vendor/products/{id}/images
// POST /vendor/products/{id}/images
public function store(Request $request, $id)
{
    $product = Product::findOrFail($id);

    $request->validate([
        'images' => 'required|array|min:1',
        'images.*' => 'required|image|mimes:jpeg,png,jpg,webp|max:4096',
        'is_primary' => 'nullable|integer|min:0',
    ]);

    $uploadedImages = [];

    foreach ($request->file('images') as $index => $file) {

        // ================= WEBP IMAGE UPLOAD =================
        $imagePath = ImageUploadService::uploadWebp(
            $file,
            'products',
            1200,
            80
        );

        // ================= CREATE DB ENTRY =================
        $image = ProductImage::create([
            'product_id' => $product->id,
            'image_url' => $imagePath,
            'is_primary' => ((int) $request->is_primary === $index),
            'sort_order' => $index
        ]);

        $uploadedImages[] = [
            'id' => $image->id,
            'image_url' => asset($image->image_url),
            'is_primary' => $image->is_primary,
            'sort_order' => $image->sort_order
        ];
    }

    return response()->json([
        'success' => true,
        'message' => 'Multiple images uploaded successfully',
        'data' => $uploadedImages
    ], 201);
}

public function deleteProductImage($id)
{
    $image = ProductImage::find($id);

    if (!$image) {

        return response()->json([
            'success' => false,
            'message' => 'Product image not found'
        ], 404);
    }

    $product = Product::find($image->product_id);

    if (!$product) {

        return response()->json([
            'success' => false,
            'message' => 'Product not found'
        ], 404);
    }

    $user = Auth::user();

    // ================= OPTIONAL AUTHORIZATION =================
    /*
    if ($product->vendor_id !== optional($user->vendor)->id) {

        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 403);
    }
    */

    // ================= DELETE IMAGE =================
    if ($image->image_url) {

        ImageUploadService::deleteImage($image->image_url);
    }

    // ================= DELETE DB RECORD =================
    $image->delete();

    return response()->json([
        'success' => true,
        'message' => 'Product image deleted successfully'
    ]);
}

}