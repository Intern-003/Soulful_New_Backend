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
    public function toggleApproval(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'commission' => 'nullable|numeric',
            'rejection_reason' => 'nullable|string'
        ]);

        $product = Product::findOrFail($id);

        $status = $request->action === 'approve' ? 'approved' : 'rejected';

        $product->update([
            'approval_status' => $status,
            'commission' => $request->commission,
            'rejection_reason' => $request->rejection_reason,
            'status' => $status === 'approved' ? 1 : 0,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Product {$status} successfully"
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

        $status = $request->action === 'approve' ? 'approved' : 'rejected';

        $products = Product::with(['user', 'vendor.user'])
            ->whereIn('id', $request->ids)
            ->get();

        foreach ($products as $product) {
            $product->update([
                'approval_status' => $status,
                'commission' => $request->commission,
                'rejection_reason' => $request->rejection_reason,
                'status' => $status === 'approved' ? 1 : 0,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

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