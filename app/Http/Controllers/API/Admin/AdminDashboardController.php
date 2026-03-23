<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Order;
use App\Models\OrderItem;

class AdminDashboardController extends Controller
{
    // ✅ DASHBOARD STATS
    public function stats()
    {
        // Users (exclude admin optionally)
        $totalUsers = User::where('role', 'user')->count();

        // Vendors
        $totalVendors = Vendor::count();

        $pendingVendors = Vendor::where('status', 'pending')->count();

        // Orders
        $totalOrders = Order::count();

        // Revenue (ONLY PAID ORDERS)
        $totalRevenue = Order::where('payment_status', 'paid')->sum('total');

        return response()->json([
            'success' => true,
            'data' => [
                'total_users' => $totalUsers,
                'total_vendors' => $totalVendors,
                'pending_vendors' => $pendingVendors,
                'total_orders' => $totalOrders,
                'total_revenue' => $totalRevenue
            ]
        ]);
    }


    // ✅ PENDING VENDORS LIST (WITH USER INFO 🔥)
    public function pendingVendors()
    {
        $vendors = Vendor::with('user:id,name,email')
            ->where('status', 'pending')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $vendors
        ]);
    }


    // ✅ REVENUE CHART (PLATFORM LEVEL)
    public function revenueChart()
    {
        $data = Order::where('payment_status', 'paid')
            ->selectRaw('DATE(created_at) as date, SUM(total) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }


    // ✅ ORDERS CHART
    public function ordersChart()
    {
        $data = Order::selectRaw('DATE(created_at) as date, COUNT(*) as orders')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}