<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shipment;
use App\Models\Order;

class ShipmentController extends Controller
{

    // GET /orders/{id}/shipment
    public function shipment($id)
    {
        // $order = Order::find($id);
        $order = Order::where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $shipments = Shipment::where('order_id', $id)
            ->with('vendor')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $shipments
        ]);
    }



    // GET /orders/{id}/tracking
    public function tracking($id)
    {
        $shipment = Shipment::where('order_id', $id)
            ->select(
                'carrier',
                'tracking_number',
                'status',
                'shipped_at',
                'delivered_at'
            )
            ->first();

        if (!$shipment) {
            return response()->json([
                'success' => false,
                'message' => 'Tracking not available'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'tracking' => $shipment
        ]);
    }

}