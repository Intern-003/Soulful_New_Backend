<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shipment;
use App\Models\Order;
use App\Models\OrderItem;

class VendorOrderController extends Controller
{

    public function createShipment(Request $request,$id)
    {

        $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'carrier' => 'required|string',
            'tracking_number' => 'required|string'
        ]);

        $order = Order::findOrFail($id);

        $shipment = Shipment::create([
            'order_id' => $order->id,
            'vendor_id' => $request->vendor_id,
            'carrier' => $request->carrier,
            'tracking_number' => $request->tracking_number,
            'status' => 'shipped',
            'shipped_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shipment created successfully',
            'data' => $shipment
        ],201);
    }
public function summary(Request $request)
{
    $vendor = $request->user()->vendor;

    if (!$vendor) {
        return response()->json([
            'success' => false,
            'message' => 'Vendor not found'
        ], 404);
    }

    $query = OrderItem::where('vendor_id', $vendor->id);

    $totalOrders = $query->distinct('order_id')->count('order_id');

    $totalRevenue = $query->sum('total');

    $pending = $query->where('status', 'pending')->count();
    $processing = $query->where('status', 'processing')->count();
    $delivered = $query->where('status', 'delivered')->count();

    return response()->json([
        'success' => true,
        'data' => [
            'total_orders' => $totalOrders,
            'total_revenue' => $totalRevenue,
            'status_breakdown' => [
                'pending' => $pending,
                'processing' => $processing,
                'delivered' => $delivered
            ]
        ]
    ]);
}

}