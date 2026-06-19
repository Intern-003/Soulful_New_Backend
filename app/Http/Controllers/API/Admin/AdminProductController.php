<?php
// app/Http/Controllers/API/Admin/AdminProductController.php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductImage;  // ✅ Add this import
use App\Models\ProductVariant;
use App\Services\ImageUploadService;
use App\Models\Vendor;
use App\Notifications\ProductStatusNotification;

class AdminProductController extends Controller
{
    /**
     * GET all products (with filters)
     */
    public function index(Request $request)
    {
        $query = Product::with(['vendor', 'user', 'category', 'images']);

        // Apply filters
        if ($request->has('approval_status')) {
            $query->where('approval_status', $request->approval_status);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $products = $query->latest()->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * Get single product details
     */
    public function show($id)
    {
        $product = Product::with([
            'vendor', 
            'vendor.settings',
            'user', 
            'category', 
            'brand', 
            'images', 
            'variants',
            'specifications'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    /**
     * Toggle product approval (approve/reject)
     */
    public function toggleApproval(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'commission' => 'nullable|numeric',
            'commission_type' => 'nullable|in:fixed,percentage',
            'rejection_reason' => 'nullable|string'
        ]);

        $product = Product::findOrFail($id);
        $status = $request->action === 'approve' ? 'approved' : 'rejected';

        $updateData = [
            'approval_status' => $status,
            'rejection_reason' => $request->rejection_reason,
            'status' => $status === 'approved' ? 1 : 0,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ];

        // Override commission if provided
        if ($request->has('commission')) {
            $updateData['commission'] = $request->commission;
        }
        if ($request->has('commission_type')) {
            $updateData['commission_type'] = $request->commission_type;
        }

        $product->update($updateData);

        // Notify vendor
        if ($product->vendor && $product->vendor->user) {
            $product->vendor->user->notify(new ProductStatusNotification($product, $status));
        }

        return response()->json([
            'success' => true,
            'message' => "Product {$status} successfully"
        ]);
    }

    /**
     * Toggle Product Status (Active/Inactive)
     */
    public function toggleStatus($id)
    {
        $product = Product::findOrFail($id);
        $newStatus = !$product->status;

        // Block invalid state
        if ($product->approval_status !== 'approved' && $newStatus) {
            return response()->json([
                'success' => false,
                'message' => 'Approve product before activating'
            ], 400);
        }

        $product->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => $newStatus ? 'Product activated' : 'Product deactivated',
            'data' => [
                'id' => $product->id,
                'status' => $product->status
            ]
        ]);
    }

    /**
     * Bulk Toggle Approval
     */
    public function bulkToggleApproval(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:products,id',
            'action' => 'required|in:approve,reject'
        ]);

        $status = $request->action === 'approve' ? 'approved' : 'rejected';

        $products = Product::with(['vendor.user'])
            ->whereIn('id', $request->ids)
            ->get();

        foreach ($products as $product) {
            $updateData = [
                'approval_status' => $status,
                'rejection_reason' => $request->rejection_reason,
                'status' => $status === 'approved' ? 1 : 0,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ];

            if ($request->has('commission')) {
                $updateData['commission'] = $request->commission;
                 $updateData['commission_type'] = $request->commission_type;
                
            }

            $product->update($updateData);

            if ($product->vendor && $product->vendor->user) {
                $product->vendor->user->notify(new ProductStatusNotification($product, $status));
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Products ' . $request->action . 'd successfully'
        ]);
    }

    /**
     * Update product commission globally
     */
    public function updateCommission(Request $request, $id)
    {
        $request->validate([
            'commission' => 'required|numeric|min:0',
            'commission_type' => 'required|in:fixed,percentage'
        ]);

        $product = Product::findOrFail($id);

        $product->update([
            'commission' => $request->commission,
            'commission_type' => $request->commission_type
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product commission updated successfully',
            'data' => $product
        ]);
    }

    /**
     * Get products pending approval
     */
    public function pendingApprovals()
    {
        $products = Product::with(['vendor', 'category'])
            ->where('approval_status', 'pending')
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * Get product statistics
     */
    public function statistics()
    {
        $stats = [
            'total_products' => Product::count(),
            'pending_approval' => Product::where('approval_status', 'pending')->count(),
            'approved_products' => Product::where('approval_status', 'approved')->count(),
            'rejected_products' => Product::where('approval_status', 'rejected')->count(),
            'active_products' => Product::where('status', 1)->count(),
            'inactive_products' => Product::where('status', 0)->count(),
            'out_of_stock' => Product::where('stock', '<=', 0)->count(),
            'low_stock' => Product::where('stock', '>', 0)->where('stock', '<=', 10)->count(),
        ];

        // Vendor-wise breakdown
        $stats['vendor_breakdown'] = Vendor::withCount(['products' => function($q) {
                $q->where('approval_status', 'approved');
            }])
            ->having('products_count', '>', 0)
            ->get(['id', 'store_name', 'products_count']);

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
      public function destroy($id)
    {
        $product = Product::findOrFail($id);

        // Delete product images
        $images = ProductImage::where('product_id', $product->id)->get();
        foreach ($images as $image) {
            if ($image->image_url) {
                ImageUploadService::deleteImage($image->image_url);
            }
            $image->delete();
        }

        // Delete variants and their images
        $variants = ProductVariant::where('product_id', $product->id)->get();
        foreach ($variants as $variant) {
            // Delete variant images
            $variantImages = ProductImage::where('variant_id', $variant->id)->get();
            foreach ($variantImages as $img) {
                if ($img->image_url) {
                    ImageUploadService::deleteImage($img->image_url);
                }
                $img->delete();
            }
            $variant->delete();
        }

        // Delete specifications
        $product->specifications()->delete();

        // Delete the product
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ], 200);
    }

}