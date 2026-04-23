<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttribute;
use App\Models\AttributeValue;
use App\Models\ProductImage;

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
    // GENERATE SKU
    // =========================
    private function generateSkuFromAttributes($ids)
    {
        return strtoupper(
            implode('-', AttributeValue::whereIn('id', $ids)->pluck('value')->toArray())
        );
    }

    // =========================
    // UNIQUE SKU
    // =========================
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

    // =========================
    // IMAGE UPLOAD HELPER
    // =========================
    private function uploadImages($files, $productId, $variantId)
    {
        if (!$files)
            return [];

        $uploadedImages = [];

        foreach ($files as $index => $file) {
            $originalName = $file->getClientOriginalName();
            $name = pathinfo($originalName, PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();

            // Generate unique filename
            $tempFilename = time() . '_' . uniqid() . '.' . $extension;
            $destination = public_path('uploads/variants');

            if (!file_exists($destination)) {
                mkdir($destination, 0755, true);
            }

            // Move file with temp name
            $file->move($destination, $tempFilename);

            // Create DB entry with image_url field
            $image = ProductImage::create([
                'product_id' => $productId,
                'variant_id' => $variantId,
                'image_url' => 'uploads/variants/' . $tempFilename,
                'is_primary' => $index === 0 ? 1 : 0,
                'sort_order' => $index
            ]);

            // Rename file with ID for better organization
            $newFilename = $name . '_' . $image->id . '.' . $extension;
            if (file_exists($destination . '/' . $tempFilename)) {
                rename(
                    $destination . '/' . $tempFilename,
                    $destination . '/' . $newFilename
                );
            }

            // Update DB with new filename
            $image->update([
                'image_url' => 'uploads/variants/' . $newFilename
            ]);

            $uploadedImages[] = $image;
        }

        // Ensure one primary image
        $variant = ProductVariant::find($variantId);
        if ($variant && $variant->images()->where('is_primary', 1)->count() === 0) {
            $first = $variant->images()->first();
            if ($first)
                $first->update(['is_primary' => 1]);
        }

        return $uploadedImages;
    }

    // =========================
    // DELETE IMAGES HELPER (NEW)
    // =========================
    private function deleteImages($imageIds)
    {
        if (!$imageIds || !is_array($imageIds))
            return;

        foreach ($imageIds as $imageId) {
            $image = ProductImage::find($imageId);
            if ($image) {
                // Delete physical file
                $path = public_path($image->image_url);
                if (file_exists($path)) {
                    unlink($path);
                }
                // Delete database record
                $image->delete();
            }
        }
    }

    // =========================
    // STORE VARIANT
    // =========================
    public function store(Request $request, $id)
    {
        $product = Product::findOrFail($id);





        $request->validate([
            'sku' => 'nullable|string',
            'barcode' => 'nullable|string',
            'price' => 'required|numeric',
            'stock' => 'required|integer',
            'weight' => 'nullable|numeric',
            'discount_price' => 'nullable|numeric',
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
        ]);

        // Upload images
        if ($request->hasFile('images')) {
            $this->uploadImages($request->file('images'), $product->id, $variant->id);
        }

        // Save attributes
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

    // =========================
    // UPDATE VARIANT (COMPLETE)
    // =========================
    public function updateVariant(Request $request, $id)
    {
        $variant = ProductVariant::findOrFail($id);

        $request->validate([
            'sku' => 'nullable|string',
            'barcode' => 'nullable|string',
            'price' => 'nullable|numeric',
            'stock' => 'nullable|integer',
            'weight' => 'nullable|numeric',
            'discount_price' => 'nullable|numeric',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
            'attribute_value_ids' => 'nullable|array',
            'delete_image_ids' => 'nullable|array', // NEW: Array of image IDs to delete
            'delete_image_ids.*' => 'integer|exists:product_images,id'
        ]);

        // Update SKU if provided
        if ($request->filled('sku')) {
            $sku = $this->normalizeSku($request->sku);
            $variant->sku = $this->ensureUniqueSku($sku, $variant->product_id, $variant->id);
        }



        // In updateVariant method, add discount_price:
        $updateData = [];
        if ($request->has('barcode'))
            $updateData['barcode'] = $request->barcode;
        if ($request->has('price'))
            $updateData['price'] = $request->price;
        if ($request->has('discount_price'))
            $updateData['discount_price'] = $request->discount_price; // Add this
        if ($request->has('stock'))
            $updateData['stock'] = $request->stock;
        if ($request->has('weight'))
            $updateData['weight'] = $request->weight;

        $variant->update($updateData);

        // Delete images that were marked for deletion
        if ($request->has('delete_image_ids') && is_array($request->delete_image_ids)) {
            $this->deleteImages($request->delete_image_ids);
        }

        // Upload new images
        if ($request->hasFile('images')) {
            $this->uploadImages(
                $request->file('images'),
                $variant->product_id,
                $variant->id
            );
        }

        // Update attributes if provided
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

    // =========================
    // DELETE VARIANT
    // =========================
    public function deleteVariant($id)
    {
        $variant = ProductVariant::findOrFail($id);

        foreach ($variant->images as $img) {
            $path = public_path($img->image_url);
            if (file_exists($path)) {
                unlink($path);
            }
            $img->delete();
        }

        $variant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Variant deleted successfully'
        ]);
    }
}