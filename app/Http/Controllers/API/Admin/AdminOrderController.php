<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;

class AdminOrderController extends Controller
{
    /**
     * 📊 Dashboard Summary
     */
    public function summary()
    {
        $totalOrders = Order::count();

        $totalRevenue = Order::where('payment_status', 'paid')->sum('total');

        $statusCounts = Order::selectRaw('order_status, COUNT(*) as count')
            ->groupBy('order_status')
            ->pluck('count', 'order_status');

        return response()->json([
            'success' => true,
            'data' => [
                'total_orders' => $totalOrders,
                'total_revenue' => $totalRevenue,
                'status_breakdown' => [
                    'pending' => $statusCounts['pending'] ?? 0,
                    'processing' => $statusCounts['processing'] ?? 0,
                    'delivered' => $statusCounts['delivered'] ?? 0,
                    'cancelled' => $statusCounts['cancelled'] ?? 0,
                ]
            ]
        ]);
    }

    /**
     * 📦 List Orders (Admin)
     * Supports filters: status, vendor_id, search
     */
    public function index(Request $request)
    {
        $query = Order::with(['user', 'items.product']);

        if ($request->status) {
            $query->where('order_status', $request->status);
        }

        if ($request->vendor_id) {
            $query->whereHas('items', function ($q) use ($request) {
                $q->where('vendor_id', $request->vendor_id);
            });
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%$search%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%$search%");
                  });
            });
        }

        $orders = $query->latest()->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * 🔍 Show Single Order - FIXED with vendor and creator relationships
     */
    public function show($id)
    {
        $order = Order::with([
            'user',
            'items.product',
            'items.vendor',      // ✅ Load vendor relationship for store name
            'items.creator',     // ✅ Load creator relationship for individual seller name
            'address'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }

    /**
     * ✏️ Update Order Status
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:pending,processing,shipped,delivered,cancelled'
        ]);

        $order = Order::findOrFail($id);
        $order->order_status = $request->status;
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully'
        ]);
    }

    /**
     * 📈 Revenue by Date Range
     */
    public function revenueByDate(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $revenue = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$request->from, $request->to])
            ->sum('total');

        return response()->json([
            'success' => true,
            'data' => [
                'from' => $request->from,
                'to' => $request->to,
                'revenue' => $revenue
            ]
        ]);
    }
}