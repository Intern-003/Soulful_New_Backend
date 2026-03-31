<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttribute;
use App\Models\AttributeValue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class VendorVariantController extends Controller
{

    // POST /vendor/products/{id}/variants
    public function store(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'sku' => 'required|string|unique:product_variants,sku',
            'barcode' => 'nullable|string',
            'price' => 'required|numeric',
            'discount_price' => 'nullable|numeric',
            'stock' => 'required|integer',
            'weight' => 'nullable|numeric',
            'image' => 'nullable|string',
            'attribute_value_ids' => 'required|array'
        ]);

        // create variant
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $request->sku,
            'barcode' => $request->barcode,
            'price' => $request->price,
            'discount_price' => $request->discount_price,
            'stock' => $request->stock,
            'weight' => $request->weight,
            'image' => $request->image
        ]);

        // attach attribute values
foreach ($request->attribute_value_ids as $valueId) {

    $value = AttributeValue::findOrFail($valueId);

    ProductVariantAttribute::create([
        'variant_id' => $variant->id,
        'attribute_id' => $value->attribute_id,
        'attribute_value_id' => $valueId
    ]);
}
        return response()->json([
            'success' => true,
            'message' => 'Variant created successfully',
            'data' => $variant
        ],201);
    }
    public function deleteVariant($id)
{
    $variant = ProductVariant::find($id);

    if (!$variant) {
        return response()->json([
            'success' => false,
            'message' => 'Variant not found'
        ], 404);
    }

    $product = Product::find($variant->product_id);

    if (!$product) {
        return response()->json([
            'success' => false,
            'message' => 'Product not found'
        ], 404);
    }

    $user = Auth::user();

    // ✅ Admin bypass (adjust if needed)
    // if (!isset($user->is_admin) || !$user->is_admin) {
    //     // ✅ Vendor ownership check
    //     if ($product->vendor_id !== $user->vendor_id) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Unauthorized to delete this variant'
    //         ], 403);
    //     }
    // }

    // Delete image if exists
    if ($variant->image) {
        $path = str_replace(url('/storage/'), 'public/', $variant->image);

        if (Storage::exists($path)) {
            Storage::delete($path);
        }
    }

    // ✅ This will also delete variant attributes (cascade)
    $variant->delete();

    return response()->json([
        'success' => true,
        'message' => 'Variant deleted successfully'
    ]);
}

public function updateVariant(Request $request, $id)
{
    $variant = ProductVariant::find($id);

    if (!$variant) {
        return response()->json([
            'success' => false,
            'message' => 'Variant not found'
        ], 404);
    }

    $product = Product::find($variant->product_id);

    // $user = Auth::user();

    // // ✅ Ownership check (admin bypass optional)
    // if (!isset($user->is_admin) || !$user->is_admin) {
    //     if ($product->vendor_id !== $user->vendor_id) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Unauthorized'
    //         ], 403);
    //     }
    // }

    // ✅ Validation
    $request->validate([
        'sku' => 'sometimes|string|unique:product_variants,sku,' . $id,
        'barcode' => 'nullable|string',
        'price' => 'sometimes|numeric',
        'discount_price' => 'nullable|numeric',
        'stock' => 'sometimes|integer',
        'weight' => 'nullable|numeric',
        'image' => 'nullable|string',
        'attribute_value_ids' => 'nullable|array'
    ]);

    // ✅ Handle image update
    if ($request->has('image')) {
        if ($variant->image) {
            $oldPath = str_replace(url('/storage/'), 'public/', $variant->image);

            if (Storage::exists($oldPath)) {
                Storage::delete($oldPath);
            }
        }

        $variant->image = $request->image;
    }

    // ✅ Update basic fields
    $variant->update($request->only([
        'sku',
        'barcode',
        'price',
        'discount_price',
        'stock',
        'weight'
    ]));

    // ✅ Update attribute values (IMPORTANT)
    if ($request->has('attribute_value_ids')) {

        // delete old mappings
        ProductVariantAttribute::where('variant_id', $variant->id)->delete();

        // add new mappings
        foreach ($request->attribute_value_ids as $valueId) {

            $value = AttributeValue::findOrFail($valueId);

            ProductVariantAttribute::create([
                'variant_id' => $variant->id,
                'attribute_id' => $value->attribute_id,
                'attribute_value_id' => $valueId
            ]);
        }
    }

    return response()->json([
        'success' => true,
        'message' => 'Variant updated successfully',
        'data' => $variant
    ]);
}


}