<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use App\Notifications\ProductStatusNotification;

class AdminProductController extends Controller
{
    // GET all products (with filters)
    public function index(Request $request)
    {
        $query = Product::with(['vendor', 'user', 'category', 'images']);

        // Apply filters
        if ($request->has('is_approved')) {
            $query->where('is_approved', $request->is_approved);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $products = $query->latest()->paginate(10);

        return response()->json($products);
    }

    // ✅ OPTIMIZED: Single endpoint for Approve/Reject toggle
    public function toggleApproval($id)
    {
        $product = Product::with(['user', 'vendor'])->findOrFail($id);

        // Toggle approval status
        $newApprovalStatus = !$product->is_approved;

        $product->update([
            'is_approved' => $newApprovalStatus,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'status' => $newApprovalStatus ? 1 : 0, // If approved, set active; if rejected, set inactive
        ]);

        // Send notification based on new status
        $status = $newApprovalStatus ? 'approved' : 'rejected';

        if ($product->user) {
            $product->user->notify(new ProductStatusNotification($product, $status));
        }
        if ($product->vendor && $product->vendor->user) {
            $product->vendor->user->notify(new ProductStatusNotification($product, $status));
        }

        return response()->json([
            'success' => true,
            'message' => $newApprovalStatus ? 'Product approved successfully' : 'Product rejected successfully',
            'data' => [
                'id' => $product->id,
                'is_approved' => $product->is_approved,
                'status' => $product->status
            ]
        ]);
    }

    // ✅ Toggle Product Status (Active/Inactive) - Separate from approval
    public function toggleStatus($id)
    {
        $product = Product::findOrFail($id);

        $newStatus = !$product->status;

        // ❌ Block invalid state
        if (!$product->is_approved && $newStatus) {
            return response()->json([
                'success' => false,
                'message' => 'Approve product before activating'
            ], 400);
        }

        $product->update([
            'status' => $newStatus
        ]);

        return response()->json([
            'success' => true,
            'message' => $newStatus ? 'Product activated' : 'Product deactivated',
            'data' => [
                'id' => $product->id,
                'status' => $product->status
            ]
        ]);
    }
    // ✅ Bulk Toggle Approval
    public function bulkToggleApproval(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:products,id',
            'action' => 'required|in:approve,reject'
        ]);

        $isApproved = $request->action === 'approve' ? 1 : 0;


        $products = Product::with(['user', 'vendor.user'])
            ->whereIn('id', $request->ids)
            ->get();

        foreach ($products as $product) {
            $product->update([
                'is_approved' => $isApproved,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'status' => $isApproved,
            ]);

            $status = $isApproved ? 'approved' : 'rejected';

            if ($product->user) {
                $product->user->notify(new ProductStatusNotification($product, $status));
            }

            if ($product->vendor && $product->vendor->user) {
                $product->vendor->user->notify(new ProductStatusNotification($product, $status));
            }
        }


        return response()->json([
            'success' => true,
            'message' => 'Products ' . $request->action . 'd successfully'
        ]);
    }
}