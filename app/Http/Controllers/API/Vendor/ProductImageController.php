<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductImage;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;


class ProductImageController extends Controller
{

    // POST /vendor/products/{id}/images
    public function store(Request $request, $id){

    $product = Product::findOrFail($id);

    $request->validate([
        'images' => 'required|array',
        'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
        'is_primary' => 'nullable|integer', // index of primary image
    ]);

    $uploadedImages = [];

foreach ($request->file('images') as $index => $file) {

    $originalName = $file->getClientOriginalName();
    $name = pathinfo($originalName, PATHINFO_FILENAME);
    $extension = $file->getClientOriginalExtension();

    // Step 1: temp filename
    $tempFilename = time() . '_' . uniqid() . '.' . $extension;

    $destination = public_path('uploads/products');

    if (!file_exists($destination)) {
        mkdir($destination, 0755, true);
    }

    // Move file with temp name
    $file->move($destination, $tempFilename);

    // Step 2: create DB entry
    $image = ProductImage::create([
        'product_id' => $product->id,
        'image_url' => 'uploads/products/' . $tempFilename,
        'is_primary' => ($request->is_primary == $index),
        'sort_order' => $index
    ]);

    // Step 3: rename file with ID
    $newFilename = $name . '_' . $image->id . '.' . $extension;

    rename(
        $destination . '/' . $tempFilename,
        $destination . '/' . $newFilename
    );

    // Step 4: update DB
    $image->update([
        'image_url' => 'uploads/products/' . $newFilename
    ]);

    $uploadedImages[] = [
        'id' => $image->id,
        'image_url' => url('uploads/products/' . $newFilename),
        'is_primary' => $image->is_primary
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

    // Admin bypass (adjust based on your system)
    // if (!isset($user->is_admin) || !$user->is_admin) {
    //     if ($product->vendor_id !== $user->vendor_id) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Unauthorized'
    //         ], 403);
    //     }
    // }

    // Handle full URL → storage path
    $path = str_replace(url('/storage/'), 'public/', $image->image_url);

    if (Storage::exists($path)) {
        Storage::delete($path);
    }

    $image->delete();

    return response()->json([
        'success' => true,
        'message' => 'Product image deleted successfully'
    ]);
}

}