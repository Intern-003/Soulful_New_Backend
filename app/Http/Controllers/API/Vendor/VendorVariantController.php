<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttribute;
use App\Models\AttributeValue;

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
}