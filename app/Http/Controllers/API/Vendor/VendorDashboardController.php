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
            ],404);
        }

        $products = Product::where('vendor_id',$vendor->id)->count();

        $orders = OrderItem::where('vendor_id',$vendor->id)->count();

        $revenue = VendorTransaction::where('vendor_id',$vendor->id)
                    ->sum('net_amount');

        return response()->json([
            'success'=>true,
            'data'=>[
                'total_products'=>$products,
                'total_orders'=>$orders,
                'total_revenue'=>$revenue
            ]
        ]);
    }



    // ✅ STATS API
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

        $totalRevenue = $query->sum('total');

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
   // ✅ REVENUE CHART API
    public function revenueChart(Request $request)
    {
        $vendor = $request->user()->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }

        // Group by date
        $data = OrderItem::where('vendor_id', $vendor->id)
            ->selectRaw('DATE(created_at) as date, SUM(total) as revenue')
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
        $vendor = Vendor::where('user_id',$request->user()->id)->first();

        $orders = OrderItem::where('vendor_id',$vendor->id)
                ->with('product','order')
                ->latest()
                ->take(10)
                ->get();

        return response()->json([
            'success'=>true,
            'data'=>$orders
        ]);
    }


    
}