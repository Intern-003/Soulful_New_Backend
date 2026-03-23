<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;

class AdminOrderController extends Controller
{
    public function summary()
{
    $totalOrders = Order::count();

    $totalRevenue = Order::where('payment_status', 'paid')->sum('total');

    $pendingOrders = Order::where('order_status', 'placed')->count();
    $processingOrders = Order::where('order_status', 'processing')->count();
    $deliveredOrders = Order::where('order_status', 'delivered')->count();
    $cancelledOrders = Order::where('order_status', 'cancelled')->count();

    return response()->json([
        'success' => true,
        'data' => [
            'total_orders' => $totalOrders,
            'total_revenue' => $totalRevenue,
            'status_breakdown' => [
                'placed' => $pendingOrders,
                'processing' => $processingOrders,
                'delivered' => $deliveredOrders,
                'cancelled' => $cancelledOrders,
            ]
        ]
    ]);
}
}
