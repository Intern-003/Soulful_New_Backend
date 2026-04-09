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
        $query = Product::with(['vendor', 'user', 'category']);

        // filters
        if ($request->has('is_approved')) {
            $query->where('is_approved', $request->is_approved);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $products = $query->latest()->paginate(10);

        return response()->json($products);
    }



    
public function approve($id)
{
    $product = Product::with('user')->findOrFail($id);

    $product->update([
        'is_approved' => 1,
        'approved_by' => auth()->id(),
        'approved_at' => now(),
        'status' => 1,
    ]);

    // send notification
    if ($product->user) {
        $product->user->notify(new ProductStatusNotification($product, 'approved'));
    }

    return response()->json(['message' => 'Product approved']);
}

    public function reject($id)
{
    $product = Product::with('user')->findOrFail($id);

    $product->update([
        'is_approved' => 0,
        'approved_by' => auth()->id(),
        'approved_at' => now(),
        'status' => 0,
    ]);

    if ($product->user) {
        $product->user->notify(new ProductStatusNotification($product, 'rejected'));
    }

    return response()->json(['message' => 'Product rejected']);
}
    public function toggleStatus($id)
    {
        $product = Product::findOrFail($id);

        $product->update([
            'status' => !$product->status
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully'
        ]);
    }

}