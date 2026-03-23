<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;
use App\Models\Vendor;

class AdminAnalyticsController extends Controller
{
    // ✅ SALES ANALYTICS (Improved)
    public function sales()
    {
        $paidOrders = Order::where('payment_status', 'paid');

        $totalSales = $paidOrders->sum('total');

        $totalOrders = $paidOrders->count();

        $averageOrderValue = $totalOrders > 0
            ? round($totalSales / $totalOrders, 2)
            : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_sales' => $totalSales,
                'paid_orders' => $totalOrders,
                'average_order_value' => $averageOrderValue
            ]
        ]);
    }

    // ✅ ORDERS ANALYTICS (More detailed)
    public function orders()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total_orders' => Order::count(),
                'placed' => Order::where('order_status', 'placed')->count(),
                'processing' => Order::where('order_status', 'processing')->count(),
                'delivered' => Order::where('order_status', 'delivered')->count(),
                'cancelled' => Order::where('order_status', 'cancelled')->count(),
                'pending_payment' => Order::where('payment_status', 'pending')->count(),
                'paid_orders' => Order::where('payment_status', 'paid')->count(),
            ]
        ]);
    }

    // ✅ VENDORS ANALYTICS (Using your relationships)
    public function vendors()
    {
        $totalVendors = Vendor::count();

        $activeVendors = Vendor::whereHas('products')->count();

        $topVendors = Vendor::withCount('products')
            ->orderByDesc('products_count')
            ->take(5)
            ->get(['id', 'store_name']);

        return response()->json([
            'success' => true,
            'data' => [
                'total_vendors' => $totalVendors,
                'active_vendors' => $activeVendors,
                'top_vendors' => $topVendors
            ]
        ]);
    }

    // ✅ PRODUCTS ANALYTICS (More useful)
    public function products()
    {
        $totalProducts = Product::count();

        $inStock = Product::where('stock', '>', 0)->count();

        $outOfStock = Product::where('stock', 0)->count();

        $lowStock = Product::where('stock', '<=', 5)->count();

        $topProducts = Product::withCount('reviews')
            ->orderByDesc('reviews_count')
            ->take(5)
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => [
                'total_products' => $totalProducts,
                'in_stock' => $inStock,
                'out_of_stock' => $outOfStock,
                'low_stock' => $lowStock,
                'top_reviewed_products' => $topProducts
            ]
        ]);
    }
}