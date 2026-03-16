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



    // GET /vendor/dashboard/stats
    public function stats(Request $request)
    {
        $vendor = Vendor::where('user_id',$request->user()->id)->first();

        $orders = OrderItem::where('vendor_id',$vendor->id)->count();

        $pendingOrders = OrderItem::where('vendor_id',$vendor->id)
                        ->where('status','pending')
                        ->count();

        $completedOrders = OrderItem::where('vendor_id',$vendor->id)
                        ->where('status','completed')
                        ->count();

        return response()->json([
            'success'=>true,
            'data'=>[
                'total_orders'=>$orders,
                'pending_orders'=>$pendingOrders,
                'completed_orders'=>$completedOrders
            ]
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