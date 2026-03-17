<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shipment;
use App\Models\Order;

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

}