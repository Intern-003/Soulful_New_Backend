<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    /**
     * DASHBOARD STATS - Updated with correct column names
     * GET /admin/dashboard/stats
     */
    public function stats()
    {
        // Users (exclude admin users - role_id = 1 is admin)
        $totalUsers = User::where('role_id', '!=', 1)->count();

        // Vendors
        $totalVendors = Vendor::count();
        $pendingVendors = Vendor::where('status', 'pending')->count();
        $activeVendors = Vendor::where('status', 'active')->count();
        $suspendedVendors = Vendor::where('status', 'suspended')->count();

        // Orders - using correct column names
        $totalOrders = Order::count();
        $pendingOrders = Order::where('order_status', 'pending')->count();
        $processingOrders = Order::where('order_status', 'processing')->count();
        $shippedOrders = Order::where('order_status', 'shipped')->count();
        $deliveredOrders = Order::where('order_status', 'delivered')->count();
        $cancelledOrders = Order::where('order_status', 'cancelled')->count();

        // Revenue (ONLY PAID ORDERS) - using grand_total
        $totalRevenue = Order::where('payment_status', 'paid')->sum('grand_total');
        
        // Today's revenue
        $todayRevenue = Order::where('payment_status', 'paid')
            ->whereDate('created_at', today())
            ->sum('grand_total');
        
        // This month's revenue
        $monthRevenue = Order::where('payment_status', 'paid')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('grand_total');
        
        // Platform commission earned
        $totalCommission = OrderItem::sum('commission_amount');
        $pendingCommission = OrderItem::where('settlement_status', 'pending')->sum('commission_amount');
        $settledCommission = OrderItem::where('settlement_status', 'settled')->sum('commission_amount');

        // Total vendor payouts
        $totalVendorPayout = OrderItem::sum('vendor_payout');
        $pendingVendorPayout = OrderItem::where('settlement_status', 'pending')->sum('vendor_payout');
        $settledVendorPayout = OrderItem::where('settlement_status', 'settled')->sum('vendor_payout');

        // Get recent orders count (last 7 days)
        $recentOrders = Order::where('created_at', '>=', Carbon::now()->subDays(7))->count();

        return response()->json([
            'success' => true,
            'data' => [
                'users' => [
                    'total' => $totalUsers,
                ],
                'vendors' => [
                    'total' => $totalVendors,
                    'pending' => $pendingVendors,
                    'active' => $activeVendors,
                    'suspended' => $suspendedVendors,
                ],
                'orders' => [
                    'total' => $totalOrders,
                    'pending' => $pendingOrders,
                    'processing' => $processingOrders,
                    'shipped' => $shippedOrders,
                    'delivered' => $deliveredOrders,
                    'cancelled' => $cancelledOrders,
                    'recent_7_days' => $recentOrders,
                ],
                'revenue' => [
                    'total' => round($totalRevenue, 2),
                    'today' => round($todayRevenue, 2),
                    'this_month' => round($monthRevenue, 2),
                ],
                'commission' => [
                    'total' => round($totalCommission, 2),
                    'pending' => round($pendingCommission, 2),
                    'settled' => round($settledCommission, 2),
                ],
                'vendor_payouts' => [
                    'total' => round($totalVendorPayout, 2),
                    'pending' => round($pendingVendorPayout, 2),
                    'settled' => round($settledVendorPayout, 2),
                ]
            ]
        ]);
    }

    /**
     * PENDING VENDORS LIST (WITH USER INFO)
     * GET /admin/dashboard/pending-vendors
     */
    public function pendingVendors(Request $request)
    {
        $query = Vendor::with('user:id,name,email,phone')
            ->where('status', 'pending');
        
        // Search filter
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('store_name', 'like', "%{$search}%")
                  ->orWhereHas('user', function($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }
        
        $vendors = $query->latest()->paginate(15);
        
        // Add additional info
        $vendors->getCollection()->transform(function ($vendor) {
            $vendor->product_count = $vendor->products()->count();
            $vendor->submitted_at = $vendor->created_at->diffForHumans();
            return $vendor;
        });
        

        return response()->json([
            'success' => true,
            'data' => $vendors
        ]);
    }

    /**
     * REVENUE CHART (PLATFORM LEVEL)
     * GET /admin/dashboard/revenue-chart
     */
    public function revenueChart(Request $request)
    {
        $days = $request->get('days', 30); // Last 30 days by default
        $startDate = Carbon::now()->subDays($days);
        
        $data = Order::where('payment_status', 'paid')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, SUM(grand_total) as revenue, COUNT(*) as orders_count')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();
        
        // Fill in missing dates with zero revenue
        $dates = [];
        $currentDate = Carbon::now()->subDays($days);
        $endDate = Carbon::now();
        
        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $found = $data->firstWhere('date', $dateStr);
            $dates[] = [
                'date' => $dateStr,
                'revenue' => $found ? round($found->revenue, 2) : 0,
                'orders_count' => $found ? $found->orders_count : 0,
                'formatted_date' => $currentDate->format('d M Y')
            ];
            $currentDate->addDay();
        }
        
        // Summary stats
        $totalRevenue = collect($dates)->sum('revenue');
        $averageDailyRevenue = $days > 0 ? round($totalRevenue / $days, 2) : 0;
        $bestDay = collect($dates)->sortByDesc('revenue')->first();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'from' => $startDate->format('Y-m-d'),
                    'to' => $endDate->format('Y-m-d'),
                    'days' => $days
                ],
                'chart_data' => $dates,
                'summary' => [
                    'total_revenue' => round($totalRevenue, 2),
                    'average_daily_revenue' => $averageDailyRevenue,
                    'best_day' => $bestDay ? [
                        'date' => $bestDay['date'],
                        'revenue' => $bestDay['revenue'],
                        'orders' => $bestDay['orders_count']
                    ] : null
                ]
            ]
        ]);
    }

    /**
     * ORDERS CHART
     * GET /admin/dashboard/orders-chart
     */
    public function ordersChart(Request $request)
    {
        $days = $request->get('days', 30);
        $startDate = Carbon::now()->subDays($days);
        
        $data = Order::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total_orders, 
                        SUM(CASE WHEN order_status = "pending" THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN order_status = "processing" THEN 1 ELSE 0 END) as processing,
                        SUM(CASE WHEN order_status = "shipped" THEN 1 ELSE 0 END) as shipped,
                        SUM(CASE WHEN order_status = "delivered" THEN 1 ELSE 0 END) as delivered,
                        SUM(CASE WHEN order_status = "cancelled" THEN 1 ELSE 0 END) as cancelled')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();
        
        // Fill in missing dates
        $dates = [];
        $currentDate = Carbon::now()->subDays($days);
        $endDate = Carbon::now();
        
        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $found = $data->firstWhere('date', $dateStr);
            $dates[] = [
                'date' => $dateStr,
                'formatted_date' => $currentDate->format('d M Y'),
                'total_orders' => $found ? $found->total_orders : 0,
                'pending' => $found ? $found->pending : 0,
                'processing' => $found ? $found->processing : 0,
                'shipped' => $found ? $found->shipped : 0,
                'delivered' => $found ? $found->delivered : 0,
                'cancelled' => $found ? $found->cancelled : 0,
            ];
            $currentDate->addDay();
        }
        
        // Summary stats
        $totalOrders = collect($dates)->sum('total_orders');
        $averageDailyOrders = $days > 0 ? round($totalOrders / $days, 2) : 0;
        
        // Status distribution (all time)
        $statusDistribution = [
            'pending' => Order::where('order_status', 'pending')->count(),
            'processing' => Order::where('order_status', 'processing')->count(),
            'shipped' => Order::where('order_status', 'shipped')->count(),
            'delivered' => Order::where('order_status', 'delivered')->count(),
            'cancelled' => Order::where('order_status', 'cancelled')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'from' => $startDate->format('Y-m-d'),
                    'to' => $endDate->format('Y-m-d'),
                    'days' => $days
                ],
                'chart_data' => $dates,
                'status_distribution' => $statusDistribution,
                'summary' => [
                    'total_orders' => $totalOrders,
                    'average_daily_orders' => $averageDailyOrders,
                    'completion_rate' => $totalOrders > 0 
                        ? round(($statusDistribution['delivered'] / $totalOrders) * 100, 2) 
                        : 0
                ]
            ]
        ]);
    }

    /**
     * TOP VENDORS BY REVENUE
     * GET /admin/dashboard/top-vendors
     */
    public function topVendors(Request $request)
    {
        $limit = $request->get('limit', 10);
        
        $topVendors = Vendor::with('user:id,name,email')
            ->withSum(['orderItems' => function($q) {
                $q->where('settlement_status', 'settled');
            }], 'vendor_payout')
            ->withCount('orderItems')
            ->get()
            ->map(function($vendor) {
                return [
                    'id' => $vendor->id,
                    'store_name' => $vendor->store_name,
                    'owner_name' => $vendor->user ? $vendor->user->name : null,
                    'total_sales' => round($vendor->order_items_sum_vendor_payout ?? 0, 2),
                    'total_items_sold' => $vendor->order_items_count,
                    'rating' => $vendor->rating ?? 0,
                    'status' => $vendor->status
                ];
            })
            ->sortByDesc('total_sales')
            ->values()
            ->take($limit);
        
        return response()->json([
            'success' => true,
            'data' => $topVendors
        ]);
    }

    /**
     * RECENT ACTIVITIES
     * GET /admin/dashboard/recent-activities
     */
    public function recentActivities(Request $request)
    {
        $limit = $request->get('limit', 10);
        
        $recentOrders = Order::with('user:id,name')
            ->latest()
            ->take($limit)
            ->get()
            ->map(function($order) {
                return [
                    'type' => 'order',
                    'description' => "New order #{$order->order_number} placed by {$order->user->name}",
                    'amount' => $order->grand_total,
                    'status' => $order->order_status,
                    'created_at' => $order->created_at->diffForHumans(),
                    'full_date' => $order->created_at->format('Y-m-d H:i:s')
                ];
            });
        
        $recentVendors = Vendor::with('user:id,name')
            ->latest()
            ->take($limit)
            ->get()
            ->map(function($vendor) {
                return [
                    'type' => 'vendor',
                    'description' => "New vendor registration: {$vendor->store_name} by {$vendor->user->name}",
                    'status' => $vendor->status,
                    'created_at' => $vendor->created_at->diffForHumans(),
                    'full_date' => $vendor->created_at->format('Y-m-d H:i:s')
                ];
            });
        
        // Merge and sort by date
        $activities = $recentOrders->concat($recentVendors)
            ->sortByDesc('full_date')
            ->take($limit)
            ->values();
        
        return response()->json([
            'success' => true,
            'data' => $activities
        ]);
    }
}