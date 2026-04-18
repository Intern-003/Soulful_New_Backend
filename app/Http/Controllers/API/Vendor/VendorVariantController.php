<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttribute;
use App\Models\AttributeValue;
use App\Models\ProductImage;
use Illuminate\Support\Str;

class VendorVariantController extends Controller
{
    // =========================
    // NORMALIZE SKU
    // =========================
    private function normalizeSku($sku)
    {
        return strtoupper(trim($sku));
    }

    // =========================
    // GENERATE SKU FROM ATTRIBUTES
    // =========================
    private function generateSkuFromAttributes($attributeValueIds)
    {
        $values = AttributeValue::whereIn('id', $attributeValueIds)
            ->pluck('value')
            ->toArray();

        return strtoupper(implode('-', $values));
    }

    // =========================
    // ENSURE UNIQUE SKU
    // =========================
    private function ensureUniqueSku($sku, $productId, $ignoreId = null)
    {
        $original = $sku;
        $counter = 1;

        while (
            ProductVariant::where('product_id', $productId)
                ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
                ->where('sku', $sku)
                ->exists()
        ) {
            $sku = $original . '-' . $counter++;
        }

        return $sku;
    }

    // =========================
    // STORE VARIANT
    // =========================
    public function store(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'sku' => ['nullable', 'string'],
            'barcode' => 'nullable|string',
            'price' => 'required|numeric',
            'discount_price' => 'nullable|numeric',
            'stock' => 'required|integer',
            'weight' => 'nullable|numeric',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
            'attribute_value_ids' => 'required|array',
            'attribute_value_ids.*' => 'exists:attribute_values,id'
        ]);

        // =========================
        // SKU LOGIC
        // =========================
        $sku = !empty($request->sku)
            ? $this->normalizeSku($request->sku)
            : $this->generateSkuFromAttributes($request->attribute_value_ids);

        $sku = $this->ensureUniqueSku($sku, $product->id);

        // =========================
        // CREATE VARIANT
        // =========================
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $sku,
            'barcode' => $request->barcode,
            'price' => $request->price,
            'discount_price' => $request->discount_price,
            'stock' => $request->stock,
            'weight' => $request->weight,
        ]);

        // =========================
        // MULTIPLE IMAGE UPLOAD
        // =========================
        if ($request->hasFile('images')) {

            foreach ($request->file('images') as $index => $file) {

                $filename = uniqid() . '.' . $file->getClientOriginalExtension();
                $path = 'uploads/variants/' . $filename;

                $file->move(public_path('uploads/variants'), $filename);

                ProductImage::create([
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'image' => $path,
                    'is_primary' => $index === 0 ? 1 : 0
                ]);
            }
        }

        // =========================
        // ENSURE PRIMARY IMAGE
        // =========================
        if ($variant->images()->where('is_primary', 1)->count() === 0) {
            $first = $variant->images()->first();
            if ($first) {
                $first->update(['is_primary' => 1]);
            }
        }

        // =========================
        // ATTACH ATTRIBUTES (OPTIMIZED)
        // =========================
        $values = AttributeValue::whereIn('id', $request->attribute_value_ids)
            ->get()
            ->keyBy('id');

        foreach ($request->attribute_value_ids as $valueId) {
            $value = $values[$valueId];

            ProductVariantAttribute::create([
                'variant_id' => $variant->id,
                'attribute_id' => $value->attribute_id,
                'attribute_value_id' => $valueId
            ]);
        }

        // =========================
        // RESPONSE FORMAT
        // =========================
        $variant->load(['images', 'attributeValues.attribute']);

        $variant->images->transform(function ($img) {
            $img->image_url = url($img->image);
            return $img;
        });

        $variant->attributes = [];
        foreach ($variant->attributeValues as $val) {
            $variant->attributes[$val->attribute->name] = $val->value;
        }
        unset($variant->attributeValues);

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

        $variantId = $variant->id;

        // delete images first
        $images = ProductImage::where('variant_id', $variantId)->get();

        foreach ($images as $img) {
            if ($img->image && file_exists(public_path($img->image))) {
                unlink(public_path($img->image));
            }
            $img->delete();
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

        $request->validate([
            'sku' => ['nullable', 'string'],
            'barcode' => 'nullable|string',
            'price' => 'sometimes|numeric',
            'discount_price' => 'nullable|numeric',
            'stock' => 'sometimes|integer',
            'weight' => 'nullable|numeric',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
            'attribute_value_ids' => 'nullable|array',
            'attribute_value_ids.*' => 'exists:attribute_values,id'
        ]);

        // =========================
        // UPDATE IMAGES
        // =========================
        if ($request->hasFile('images')) {

            $oldImages = ProductImage::where('variant_id', $variant->id)->get();

            foreach ($oldImages as $img) {
                if ($img->image && file_exists(public_path($img->image))) {
                    unlink(public_path($img->image));
                }
                $img->delete();
            }

            foreach ($request->file('images') as $index => $file) {

                $filename = uniqid() . '.' . $file->getClientOriginalExtension();
                $path = 'uploads/variants/' . $filename;

                $file->move(public_path('uploads/variants'), $filename);

                ProductImage::create([
                    'product_id' => $variant->product_id,
                    'variant_id' => $variant->id,
                    'image' => $path,
                    'is_primary' => $index === 0 ? 1 : 0
                ]);
            }
        }

        // =========================
        // SKU LOGIC (SAFE)
        // =========================
        if ($request->has('sku')) {

            $sku = !empty($request->sku)
                ? $this->normalizeSku($request->sku)
                : $variant->sku;

            $variant->sku = $this->ensureUniqueSku($sku, $variant->product_id, $variant->id);
        }

        // =========================
        // UPDATE FIELDS
        // =========================
        $variant->update($request->only([
            'barcode',
            'price',
            'discount_price',
            'stock',
            'weight'
        ]));

        // =========================
        // UPDATE ATTRIBUTES
        // =========================
        if ($request->has('attribute_value_ids')) {

            ProductVariantAttribute::where('variant_id', $variant->id)->delete();

            $values = AttributeValue::whereIn('id', $request->attribute_value_ids)
                ->get()
                ->keyBy('id');

            foreach ($request->attribute_value_ids as $valueId) {
                $value = $values[$valueId];

                ProductVariantAttribute::create([
                    'variant_id' => $variant->id,
                    'attribute_id' => $value->attribute_id,
                    'attribute_value_id' => $valueId
                ]);
            }
        }

        // =========================
        // RESPONSE
        // =========================
        $variant->load(['images', 'attributeValues.attribute']);

        $variant->images->transform(function ($img) {
            $img->image_url = url($img->image);
            return $img;
        });

        $variant->attributes = [];
        foreach ($variant->attributeValues as $val) {
            $variant->attributes[$val->attribute->name] = $val->value;
        }
        unset($variant->attributeValues);

        return response()->json([
            'success' => true,
            'message' => 'Variant updated successfully',
            'data' => $variant
        ]);
    }
}