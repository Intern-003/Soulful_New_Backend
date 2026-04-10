<?php

// namespace App\Http\Controllers\API\Admin;

// use App\Http\Controllers\Controller;
// use Illuminate\Http\Request;
// use App\Models\Order;

// class AdminOrderController extends Controller
// {
//     public function summary()
// {
//     $totalOrders = Order::count();

//     $totalRevenue = Order::where('payment_status', 'paid')->sum('total');

//     $pendingOrders = Order::where('order_status', 'placed')->count();
//     $processingOrders = Order::where('order_status', 'processing')->count();
//     $deliveredOrders = Order::where('order_status', 'delivered')->count();
//     $cancelledOrders = Order::where('order_status', 'cancelled')->count();

//     return response()->json([
//         'success' => true,
//         'data' => [
//             'total_orders' => $totalOrders,
//             'total_revenue' => $totalRevenue,
//             'status_breakdown' => [
//                 'placed' => $pendingOrders,
//                 'processing' => $processingOrders,
//                 'delivered' => $deliveredOrders,
//                 'cancelled' => $cancelledOrders,
//             ]
//         ]
//     ]);
// }
// }





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

        // Filter by status
        if ($request->status) {
            $query->where('order_status', $request->status);
        }

        // Filter by vendor (via order_items)
        if ($request->vendor_id) {
            $query->whereHas('items', function ($q) use ($request) {
                $q->where('vendor_id', $request->vendor_id);
            });
        }

        // Search by order number or user
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
     * 🔍 Show Single Order
     */
    public function show($id)
    {
        $order = Order::with(['user', 'items.product'])
            ->findOrFail($id);

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
            'status' => 'required|string|in:pending,processing,delivered,cancelled'
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