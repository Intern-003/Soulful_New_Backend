<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;

class AdminReportController extends Controller
{
    // ✅ SALES REPORT
    public function sales()
    {
        $totalRevenue = Order::where('payment_status', 'paid')->sum('total');

        $totalOrders = Order::count();

        $averageOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue' => $totalRevenue,
                'total_orders' => $totalOrders,
                'average_order_value' => round($averageOrderValue, 2)
            ]
        ]);
    }


    // ✅ VENDOR SALES REPORT
    public function vendorSales()
    {
        $data = OrderItem::with('vendor:id,store_name')
            ->selectRaw('vendor_id, SUM(total) as total_sales, COUNT(DISTINCT order_id) as total_orders')
            ->groupBy('vendor_id')
            ->orderByDesc('total_sales')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }


    // ✅ PRODUCT SALES REPORT
    public function productSales()
    {
        $data = OrderItem::with('product:id,name')
            ->selectRaw('product_id, SUM(quantity) as total_sold, SUM(total) as revenue')
            ->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }


    // ✅ CUSTOMER REPORT
    public function customers()
    {
        $data = User::withCount('orders')
            ->withSum('orders as total_spent', 'total')
            ->where('role', 'user')
            ->orderByDesc('total_spent')
            ->get(['id', 'name', 'email']);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}