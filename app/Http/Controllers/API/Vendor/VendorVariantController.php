<?php
// app/Http/Controllers/API/Vendor/VendorVariantController.php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttribute;
use App\Models\AttributeValue;
use App\Models\ProductImage;
use App\Services\ImageUploadService;

class VendorVariantController extends Controller
{
    private function normalizeSku($sku)
    {
        return strtoupper(trim($sku));
    }

    private function generateSkuFromAttributes($ids)
    {
        return strtoupper(
            implode('-', AttributeValue::whereIn('id', $ids)->pluck('value')->toArray())
        );
    }

    private function ensureUniqueSku($sku, $productId, $ignoreId = null)
    {
        $base = $sku;
        $i = 1;

        while (
            ProductVariant::where('product_id', $productId)
                ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
                ->where('sku', $sku)
                ->exists()
        ) {
            $sku = $base . '-' . $i++;
        }

        return $sku;
    }

    private function uploadImages($files, $productId, $variantId)
    {
        if (!$files) {
            return [];
        }

        $uploadedImages = [];

        foreach ($files as $index => $file) {
            $imagePath = ImageUploadService::uploadWebp(
                $file,
                'variants',
                1200,
                80
            );

            $image = ProductImage::create([
                'product_id' => $productId,
                'variant_id' => $variantId,
                'image_url' => $imagePath,
                'is_primary' => $index === 0 ? 1 : 0,
                'sort_order' => $index
            ]);

            $uploadedImages[] = $image;
        }

        $variant = ProductVariant::find($variantId);

        if ($variant && $variant->images()->where('is_primary', 1)->count() === 0) {
            $first = $variant->images()->first();
            if ($first) {
                $first->update(['is_primary' => 1]);
            }
        }

        return $uploadedImages;
    }

    private function deleteImages($imageIds)
    {
        if (!$imageIds || !is_array($imageIds)) {
            return;
        }

        foreach ($imageIds as $imageId) {
            $image = ProductImage::find($imageId);
            if ($image) {
                if ($image->image_url) {
                    ImageUploadService::deleteImage($image->image_url);
                }
                $image->delete();
            }
        }
    }

    /**
     * STORE VARIANT - Enhanced with tax, shipping, cost price
     */
    public function store(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'sku' => 'nullable|string',
            'barcode' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'weight' => 'nullable|numeric',
            'discount_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0', // NEW
            'tax_rate' => 'nullable|numeric|min:0|max:100', // NEW
            'shipping_charge' => 'nullable|numeric|min:0', // NEW
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
            'attribute_value_ids' => 'required|array'
        ]);

        $sku = $request->sku
            ? $this->normalizeSku($request->sku)
            : $this->generateSkuFromAttributes($request->attribute_value_ids);

        $sku = $this->ensureUniqueSku($sku, $product->id);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $sku,
            'barcode' => $request->barcode,
            'price' => $request->price,
            'stock' => $request->stock,
            'weight' => $request->weight,
            'discount_price' => $request->discount_price ?? null,
            'cost_price' => $request->cost_price ?? null, // NEW
            'tax_rate' => $request->tax_rate ?? $product->tax_rate, // NEW - fallback to product tax
            'shipping_charge' => $request->shipping_charge ?? $product->shipping_charge, // NEW
        ]);

        if ($request->hasFile('images')) {
            $this->uploadImages($request->file('images'), $product->id, $variant->id);
        }

        foreach ($request->attribute_value_ids as $attrValueId) {
            $val = AttributeValue::find($attrValueId);
            if ($val) {
                ProductVariantAttribute::create([
                    'variant_id' => $variant->id,
                    'attribute_id' => $val->attribute_id,
                    'attribute_value_id' => $attrValueId
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Variant created successfully',
            'data' => $variant->load(['images', 'attributeValues.attribute'])
        ], 201);
    }

    /**
     * UPDATE VARIANT - Enhanced with all fields
     */
    public function updateVariant(Request $request, $id)
    {
        $variant = ProductVariant::findOrFail($id);

        $request->validate([
            'sku' => 'nullable|string',
            'barcode' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'weight' => 'nullable|numeric',
            'discount_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0', // NEW
            'tax_rate' => 'nullable|numeric|min:0|max:100', // NEW
            'shipping_charge' => 'nullable|numeric|min:0', // NEW
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
            'attribute_value_ids' => 'nullable|array',
            'delete_image_ids' => 'nullable|array',
            'delete_image_ids.*' => 'integer|exists:product_images,id'
        ]);

        if ($request->filled('sku')) {
            $sku = $this->normalizeSku($request->sku);
            $variant->sku = $this->ensureUniqueSku($sku, $variant->product_id, $variant->id);
        }

        $updateData = [];
        if ($request->has('barcode')) $updateData['barcode'] = $request->barcode;
        if ($request->has('price')) $updateData['price'] = $request->price;
        if ($request->has('discount_price')) $updateData['discount_price'] = $request->discount_price;
        if ($request->has('cost_price')) $updateData['cost_price'] = $request->cost_price; // NEW
        if ($request->has('tax_rate')) $updateData['tax_rate'] = $request->tax_rate; // NEW
        if ($request->has('shipping_charge')) $updateData['shipping_charge'] = $request->shipping_charge; // NEW
        if ($request->has('stock')) $updateData['stock'] = $request->stock;
        if ($request->has('weight')) $updateData['weight'] = $request->weight;

        $variant->update($updateData);

        if ($request->has('delete_image_ids') && is_array($request->delete_image_ids)) {
            $this->deleteImages($request->delete_image_ids);
        }

        if ($request->hasFile('images')) {
            $this->uploadImages(
                $request->file('images'),
                $variant->product_id,
                $variant->id
            );
        }

        if ($request->has('attribute_value_ids')) {
            ProductVariantAttribute::where('variant_id', $variant->id)->delete();

            foreach ($request->attribute_value_ids as $attrValueId) {
                $val = AttributeValue::find($attrValueId);
                if ($val) {
                    ProductVariantAttribute::create([
                        'variant_id' => $variant->id,
                        'attribute_id' => $val->attribute_id,
                        'attribute_value_id' => $attrValueId
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Variant updated successfully',
            'data' => $variant->load(['images', 'attributeValues.attribute'])
        ]);
    }

    /**
     * DELETE VARIANT
     */
    public function deleteVariant($id)
    {
        $variant = ProductVariant::findOrFail($id);

        foreach ($variant->images as $img) {
            if ($img->image_url) {
                ImageUploadService::deleteImage($img->image_url);
            }
            $img->delete();
        }

        ProductVariantAttribute::where('variant_id', $variant->id)->delete();
        $variant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Variant deleted successfully'
        ]);
    }

    /**
     * GET VARIANT DETAILS
     */
    public function getVariant($id)
    {
        $variant = ProductVariant::with([
            'product',
            'product.vendor',
            'images',
            'attributeValues.attribute'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $variant->id,
                'product_id' => $variant->product_id,
                'product_name' => $variant->product->name,
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'price' => $variant->price,
                'discount_price' => $variant->discount_price,
                'cost_price' => $variant->cost_price,
                'stock' => $variant->stock,
                'weight' => $variant->weight,
                'tax_rate' => $variant->tax_rate,
                'shipping_charge' => $variant->shipping_charge,
                'images' => $variant->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'image_url' => $image->image_url,
                        'is_primary' => $image->is_primary
                    ];
                }),
                'attributes' => $variant->attributeValues->map(function ($val) {
                    return [
                        'attribute_id' => $val->attribute_id,
                        'attribute_name' => $val->attribute->name,
                        'value_id' => $val->id,
                        'value' => $val->value,
                    ];
                })
            ]
        ]);
    }
}