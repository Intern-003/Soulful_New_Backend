<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vendor;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\VendorTransaction;

class VendorDashboardController extends Controller
{

    // GET /vendor/dashboard
    public function dashboard(Request $request)
    {
        $vendor = Vendor::where('user_id', $request->user()->id)->first();

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }

        $products = Product::where('vendor_id', $vendor->id)->count();
        $orders = OrderItem::where('vendor_id', $vendor->id)->count();
        $revenue = VendorTransaction::where('vendor_id', $vendor->id)
            ->sum('net_amount');

        return response()->json([
            'success' => true,
            'data' => [
                'total_products' => $products,
                'total_orders' => $orders,
                'total_revenue' => $revenue
            ]
        ]);
    }

    // ✅ STATS API - FIXED (removed 'total' column)
    public function stats(Request $request)
    {
        $vendor = $request->user()->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }

        $query = OrderItem::where('vendor_id', $vendor->id);

        // ✅ FIXED: Use 'selling_price' instead of 'total'
        $totalRevenue = $query->sum('selling_price');

        $totalOrders = $query->distinct('order_id')->count('order_id');

        $totalProducts = Product::where('vendor_id', $vendor->id)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue' => $totalRevenue,
                'total_orders' => $totalOrders,
                'total_products' => $totalProducts
            ]
        ]);
    }


//         public function revenueChart(Request $request)
// {
//     $vendor = $request->user()->vendor;

//     if (!$vendor) {
//         return response()->json([
//             'success' => false,
//             'message' => 'Vendor not found'
//         ], 404);
//     }

//     // ✅ Option 1: Revenue based on selling_price
//     $data = OrderItem::where('vendor_id', $vendor->id)
//         ->selectRaw('DATE(created_at) as date, SUM(selling_price) as revenue')
//         ->groupBy('date')
//         ->orderBy('date', 'asc')
//         ->get();

//     // ✅ Option 2: Revenue based on final_price (after discounts)
//     // $data = OrderItem::where('vendor_id', $vendor->id)
//     //     ->selectRaw('DATE(created_at) as date, SUM(final_price) as revenue')
//     //     ->groupBy('date')
//     //     ->orderBy('date', 'asc')
//     //     ->get();

//     // ✅ Option 3: Revenue based on vendor_payout (after commission)
//     // $data = OrderItem::where('vendor_id', $vendor->id)
//     //     ->selectRaw('DATE(created_at) as date, SUM(vendor_payout) as revenue')
//     //     ->groupBy('date')
//     //     ->orderBy('date', 'asc')
//     //     ->get();

//     return response()->json([
//         'success' => true,
//         'data' => $data
//     ]);
// }


    // ✅ REVENUE CHART API - FIXED
    public function revenueChart(Request $request)
    {
        $vendor = $request->user()->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }

        // ✅ FIXED: Use 'selling_price' instead of 'total'
        $data = OrderItem::where('vendor_id', $vendor->id)
            ->selectRaw('DATE(created_at) as date, SUM(selling_price) as revenue')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    // GET /vendor/orders/summary
    public function ordersSummary(Request $request)
    {
        $vendor = Vendor::where('user_id', $request->user()->id)->first();

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }

        $orders = OrderItem::where('vendor_id', $vendor->id)
            ->with('product', 'order')
            ->latest()
            ->take(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }
}