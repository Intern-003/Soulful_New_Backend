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
    public function store(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'image_url' => 'required|string',
            'is_primary' => 'nullable|boolean',
            'sort_order' => 'nullable|integer'
        ]);

        $image = ProductImage::create([
            'product_id' => $product->id,
            'image_url' => $request->image_url,
            'is_primary' => $request->is_primary ?? false,
            'sort_order' => $request->sort_order ?? 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product image uploaded successfully',
            'data' => $image
        ],201);
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
    if (!isset($user->is_admin) || !$user->is_admin) {
        if ($product->vendor_id !== $user->vendor_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }
    }

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