<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\Review;
use Carbon\Carbon;

class AdminAnalyticsController extends Controller
{
    /**
     * SALES ANALYTICS - Updated with correct column names
     * GET /admin/analytics/sales
     */
    public function sales(Request $request)
    {
        // Date range filter
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        
        $query = Order::where('payment_status', 'paid');
        
        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }
        
        $paidOrders = $query;
        
        // Use 'grand_total' instead of 'total'
        $totalSales = $paidOrders->sum('grand_total');
        $totalOrders = $paidOrders->count();
        
        $averageOrderValue = $totalOrders > 0
            ? round($totalSales / $totalOrders, 2)
            : 0;
        
        // Platform commission earned
        $totalCommission = OrderItem::whereHas('order', function($q) use ($fromDate, $toDate) {
            $q->where('payment_status', 'paid');
            if ($fromDate) $q->whereDate('created_at', '>=', $fromDate);
            if ($toDate) $q->whereDate('created_at', '<=', $toDate);
        })->sum('commission_amount');
        
        // Total vendor payouts
        $totalVendorPayout = OrderItem::whereHas('order', function($q) use ($fromDate, $toDate) {
            $q->where('payment_status', 'paid');
            if ($fromDate) $q->whereDate('created_at', '>=', $fromDate);
            if ($toDate) $q->whereDate('created_at', '<=', $toDate);
        })->sum('vendor_payout');
        
        // Daily/Monthly breakdown
        $groupBy = $request->get('group_by', 'day'); // day, month, year
        
        if ($groupBy === 'day') {
            $salesData = $paidOrders->selectRaw('DATE(created_at) as period, SUM(grand_total) as total')
                ->groupBy('period')
                ->orderBy('period', 'desc')
                ->limit(30)
                ->get();
        } elseif ($groupBy === 'month') {
            $salesData = $paidOrders->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as period, SUM(grand_total) as total')
                ->groupBy('period')
                ->orderBy('period', 'desc')
                ->limit(12)
                ->get();
        } else {
            $salesData = $paidOrders->selectRaw('YEAR(created_at) as period, SUM(grand_total) as total')
                ->groupBy('period')
                ->orderBy('period', 'desc')
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_sales' => round($totalSales, 2),
                    'total_orders' => $totalOrders,
                    'average_order_value' => $averageOrderValue,
                    'total_commission' => round($totalCommission, 2),
                    'total_vendor_payout' => round($totalVendorPayout, 2),
                ],
                'breakdown' => $salesData,
                'filters' => [
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'group_by' => $groupBy
                ]
            ]
        ]);
    }

    /**
     * ORDERS ANALYTICS - Updated with correct status values
     * GET /admin/analytics/orders
     */
    public function orders(Request $request)
    {
        // Date range filter
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        
        $query = Order::query();
        if ($fromDate) $query->whereDate('created_at', '>=', $fromDate);
        if ($toDate) $query->whereDate('created_at', '<=', $toDate);
        
        // Use 'order_status' instead of 'order_status' (it's correct)
        $statusBreakdown = [
            'pending' => (clone $query)->where('order_status', 'pending')->count(),
            'processing' => (clone $query)->where('order_status', 'processing')->count(),
            'confirmed' => (clone $query)->where('order_status', 'confirmed')->count(),
            'shipped' => (clone $query)->where('order_status', 'shipped')->count(),
            'delivered' => (clone $query)->where('order_status', 'delivered')->count(),
            'cancelled' => (clone $query)->where('order_status', 'cancelled')->count(),
        ];
        
        $paymentBreakdown = [
            'pending' => (clone $query)->where('payment_status', 'pending')->count(),
            'paid' => (clone $query)->where('payment_status', 'paid')->count(),
            'failed' => (clone $query)->where('payment_status', 'failed')->count(),
            'refunded' => (clone $query)->where('payment_status', 'refunded')->count(),
        ];
        
        // Orders by payment method
        $paymentMethodBreakdown = (clone $query)
            ->selectRaw('payment_method, COUNT(*) as count, SUM(grand_total) as total')
            ->groupBy('payment_method')
            ->get();
        
        // Today's orders
        $todayOrders = (clone $query)->whereDate('created_at', today())->count();
        $todayRevenue = (clone $query)->whereDate('created_at', today())->where('payment_status', 'paid')->sum('grand_total');
        
        // This week's orders
        $weekOrders = (clone $query)->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->count();
        
        // This month's orders
        $monthOrders = (clone $query)->whereMonth('created_at', Carbon::now()->month)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_orders' => (clone $query)->count(),
                    'today_orders' => $todayOrders,
                    'this_week_orders' => $weekOrders,
                    'this_month_orders' => $monthOrders,
                    'today_revenue' => round($todayRevenue, 2),
                ],
                'status_breakdown' => $statusBreakdown,
                'payment_breakdown' => $paymentBreakdown,
                'payment_method_breakdown' => $paymentMethodBreakdown,
                'filters' => [
                    'from_date' => $fromDate,
                    'to_date' => $toDate
                ]
            ]
        ]);
    }

    /**
     * VENDORS ANALYTICS - Enhanced with more metrics
     * GET /admin/analytics/vendors
     */
    public function vendors(Request $request)
    {
        $totalVendors = Vendor::count();
        
        $activeVendors = Vendor::where('status', 'active')->count();
        $pendingVendors = Vendor::where('status', 'pending')->count();
        $suspendedVendors = Vendor::where('status', 'suspended')->count();
        
        // Vendors with products (active sellers)
        $vendorsWithProducts = Vendor::has('products')->count();
        
        // Vendors with sales (have order items)
        $vendorsWithSales = Vendor::whereHas('orderItems')->count();
        
        // Top vendors by revenue
        $topVendorsByRevenue = Vendor::with('user:id,name,email')
            ->withSum('orderItems', 'vendor_payout')
            ->having('order_items_sum_vendor_payout', '>', 0)
            ->orderByDesc('order_items_sum_vendor_payout')
            ->take(5)
            ->get(['id', 'store_name', 'commission_rate', 'status'])
            ->map(function($vendor) {
                return [
                    'id' => $vendor->id,
                    'store_name' => $vendor->store_name,
                    'total_revenue' => round($vendor->order_items_sum_vendor_payout ?? 0, 2),
                    'commission_rate' => $vendor->commission_rate,
                    'status' => $vendor->status,
                    'owner' => $vendor->user ? $vendor->user->name : null
                ];
            });
        
        // Top vendors by orders
        $topVendorsByOrders = Vendor::with('user:id,name')
            ->withCount('orderItems')
            ->orderByDesc('order_items_count')
            ->take(5)
            ->get(['id', 'store_name'])
            ->map(function($vendor) {
                return [
                    'id' => $vendor->id,
                    'store_name' => $vendor->store_name,
                    'total_orders' => $vendor->order_items_count
                ];
            });
        
        // New vendors this month
        $newVendorsThisMonth = Vendor::whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_vendors' => $totalVendors,
                    'active_vendors' => $activeVendors,
                    'pending_vendors' => $pendingVendors,
                    'suspended_vendors' => $suspendedVendors,
                    'vendors_with_products' => $vendorsWithProducts,
                    'vendors_with_sales' => $vendorsWithSales,
                    'new_vendors_this_month' => $newVendorsThisMonth,
                ],
                'top_vendors_by_revenue' => $topVendorsByRevenue,
                'top_vendors_by_orders' => $topVendorsByOrders,
            ]
        ]);
    }

    /**
     * PRODUCTS ANALYTICS - Enhanced with sales data
     * GET /admin/analytics/products
     */
    public function products(Request $request)
    {
        $totalProducts = Product::count();
        
        // Stock status
        $inStock = Product::where('stock', '>', 0)->count();
        $outOfStock = Product::where('stock', '=', 0)->count();
        $lowStock = Product::where('stock', '>', 0)->where('stock', '<=', 5)->count();
        
        // Product status
        $approvedProducts = Product::where('approval_status', 'approved')->count();
        $pendingApproval = Product::where('approval_status', 'pending')->count();
        $rejectedProducts = Product::where('approval_status', 'rejected')->count();
        
        // Top selling products (by quantity)
        $topSellingProducts = Product::with('vendor:id,store_name')
            ->withSum('orderItems', 'quantity')
            ->having('order_items_sum_quantity', '>', 0)
            ->orderByDesc('order_items_sum_quantity')
            ->take(5)
            ->get(['id', 'name', 'price', 'discount_price', 'vendor_id'])
            ->map(function($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'selling_price' => $product->discount_price ?? $product->price,
                    'total_sold' => $product->order_items_sum_quantity ?? 0,
                    'vendor' => $product->vendor ? $product->vendor->store_name : null
                ];
            });
        
        // Top products by revenue
        $topRevenueProducts = Product::with('vendor:id,store_name')
            ->withSum('orderItems', 'vendor_payout')
            ->having('order_items_sum_vendor_payout', '>', 0)
            ->orderByDesc('order_items_sum_vendor_payout')
            ->take(5)
            ->get(['id', 'name'])
            ->map(function($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'total_revenue' => round($product->order_items_sum_vendor_payout ?? 0, 2)
                ];
            });
        
        // Categories with most products
        $topCategories = \App\Models\Category::withCount('products')
            ->having('products_count', '>', 0)
            ->orderByDesc('products_count')
            ->take(5)
            ->get(['id', 'name', 'products_count']);
        
        // Recently added products
        $recentProducts = Product::with('vendor:id,store_name')
            ->latest()
            ->take(10)
            ->get(['id', 'name', 'price', 'approval_status', 'created_at'])
            ->map(function($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'status' => $product->approval_status,
                    'added_at' => $product->created_at->diffForHumans()
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_products' => $totalProducts,
                    'in_stock' => $inStock,
                    'out_of_stock' => $outOfStock,
                    'low_stock' => $lowStock,
                    'approved_products' => $approvedProducts,
                    'pending_approval' => $pendingApproval,
                    'rejected_products' => $rejectedProducts,
                ],
                'top_selling_products' => $topSellingProducts,
                'top_revenue_products' => $topRevenueProducts,
                'top_categories' => $topCategories,
                'recent_products' => $recentProducts,
            ]
        ]);
    }
    
    /**
     * CUSTOMER ANALYTICS
     * GET /admin/analytics/customers
     */
    public function customers(Request $request)
    {
        $totalCustomers = \App\Models\User::where('role_id', 3)->count(); // role_id 3 = customer
        
        // Customers who have placed orders
        $customersWithOrders = \App\Models\User::whereHas('orders')->count();
        
        // New customers this month
        $newCustomersThisMonth = \App\Models\User::where('role_id', 3)
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();
        
        // Top customers by order value
        $topCustomers = \App\Models\User::withSum('orders', 'grand_total')
            ->whereHas('orders')
            ->orderByDesc('orders_sum_grand_total')
            ->take(5)
            ->get(['id', 'name', 'email'])
            ->map(function($customer) {
                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'total_spent' => round($customer->orders_sum_grand_total ?? 0, 2),
                    'orders_count' => $customer->orders->count()
                ];
            });
        
        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_customers' => $totalCustomers,
                    'customers_with_orders' => $customersWithOrders,
                    'new_customers_this_month' => $newCustomersThisMonth,
                ],
                'top_customers' => $topCustomers
            ]
        ]);
    }
}