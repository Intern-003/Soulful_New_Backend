<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttribute;
use App\Models\AttributeValue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VendorVariantController extends Controller
{
    // =========================
    // STORE VARIANT
    // =========================
    public function store(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'sku' => [
                'required',
                'string',
                Rule::unique('product_variants', 'sku')
                    ->where(fn ($query) => $query->where('product_id', $product->id)),
            ],
            'barcode' => 'nullable|string',
            'price' => 'required|numeric',
            'discount_price' => 'nullable|numeric',
            'stock' => 'required|integer',
            'weight' => 'nullable|numeric',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'attribute_value_ids' => 'required|array',
            'attribute_value_ids.*' => 'exists:attribute_values,id'
        ]);

        // =========================
        // IMAGE UPLOAD (UPDATED)
        // =========================
        $imagePath = null;

        if ($request->hasFile('image')) {

            $file = $request->file('image');

            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();

            $slug = Str::slug($originalName);

            $filename = $slug . '_' . uniqid() . '.' . $extension;

            $destination = public_path('uploads/variants');

            if (!file_exists($destination)) {
                mkdir($destination, 0755, true);
            }

            $file->move($destination, $filename);

            $imagePath = 'uploads/variants/' . $filename;
        }

        // =========================
        // CREATE VARIANT
        // =========================
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $request->sku,
            'barcode' => $request->barcode,
            'price' => $request->price,
            'discount_price' => $request->discount_price,
            'stock' => $request->stock,
            'weight' => $request->weight,
            'image' => $imagePath
        ]);

        // Attach attributes
        foreach ($request->attribute_value_ids as $valueId) {
            $value = AttributeValue::findOrFail($valueId);

            ProductVariantAttribute::create([
                'variant_id' => $variant->id,
                'attribute_id' => $value->attribute_id,
                'attribute_value_id' => $valueId
            ]);
        }

        $variant->image_url = $variant->image ? url($variant->image) : null;

        return response()->json([
            'success' => true,
            'message' => 'Variant created successfully',
            'data' => $variant
        ], 201);
    }

    // =========================
    // DELETE VARIANT
    // =========================
    public function deleteVariant($id)
    {
        $variant = ProductVariant::find($id);

        if (!$variant) {
            return response()->json([
                'success' => false,
                'message' => 'Variant not found'
            ], 404);
        }

        // Delete image
        if ($variant->image && file_exists(public_path($variant->image))) {
            unlink(public_path($variant->image));
        }

        $variant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Variant deleted successfully'
        ]);
    }

    // =========================
    // UPDATE VARIANT
    // =========================
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

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        $request->validate([
            'sku' => [
                'sometimes',
                'string',
                Rule::unique('product_variants', 'sku')
                    ->ignore($id)
                    ->where(fn ($query) => $query->where('product_id', $variant->product_id)),
            ],
            'barcode' => 'nullable|string',
            'price' => 'sometimes|numeric',
            'discount_price' => 'nullable|numeric',
            'stock' => 'sometimes|integer',
            'weight' => 'nullable|numeric',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'attribute_value_ids' => 'nullable|array',
            'attribute_value_ids.*' => 'exists:attribute_values,id'
        ]);

        // =========================
        // IMAGE UPDATE (UPDATED)
        // =========================
        if ($request->hasFile('image')) {

            // delete old image
            if ($variant->image && file_exists(public_path($variant->image))) {
                unlink(public_path($variant->image));
            }

            $file = $request->file('image');

            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();

            $slug = Str::slug($originalName);

            $filename = $slug . '_' . uniqid() . '.' . $extension;

            $destination = public_path('uploads/variants');

            if (!file_exists($destination)) {
                mkdir($destination, 0755, true);
            }

            $file->move($destination, $filename);

            $variant->image = 'uploads/variants/' . $filename;
        }

        // Update fields
        $variant->update($request->only([
            'sku',
            'barcode',
            'price',
            'discount_price',
            'stock',
            'weight'
        ]));

        // Update attributes
        if ($request->has('attribute_value_ids')) {

            ProductVariantAttribute::where('variant_id', $variant->id)->delete();

            foreach ($request->attribute_value_ids as $valueId) {

                $value = AttributeValue::findOrFail($valueId);

                ProductVariantAttribute::create([
                    'variant_id' => $variant->id,
                    'attribute_id' => $value->attribute_id,
                    'attribute_value_id' => $valueId
                ]);
            }
        }

        $variant->image_url = $variant->image ? url($variant->image) : null;

        return response()->json([
            'success' => true,
            'message' => 'Variant updated successfully',
            'data' => $variant
        ]);
    }
}