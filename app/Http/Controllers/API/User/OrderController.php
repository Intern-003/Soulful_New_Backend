<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;

class OrderController extends Controller
{

    // GET /orders
    public function index(Request $request)
    {
        $orders = Order::with(['items.product','address'])
            ->where('user_id',$request->user()->id)
            ->latest()
            ->paginate(10);

        return response()->json([
            'success'=>true,
            'data'=>$orders
        ]);
    }


    // GET /orders/{id}
    public function show($id)
    {
        $order = Order::with([
            'items.product',
            'items.variant',
            'items.vendor',
            'address',
            'payments',
            'shipments'
        ])->findOrFail($id);

        return response()->json([
            'success'=>true,
            'data'=>$order
        ]);
    }


    // GET /orders/{id}/track
    public function track($id)
    {
        $order = Order::select('id','order_number','order_status')
            ->findOrFail($id);

        return response()->json([
            'success'=>true,
            'tracking_status'=>$order->order_status
        ]);
    }


    // GET /orders/{id}/shipment
    public function shipment($id)
    {
        $order = Order::with('shipments')
            ->findOrFail($id);

        return response()->json([
            'success'=>true,
            'shipments'=>$order->shipments
        ]);
    }


    // GET /orders/{id}/tracking
    public function tracking($id)
    {
        $shipment = Order::with('shipments')
            ->findOrFail($id)
            ->shipments;

        return response()->json([
            'success'=>true,
            'tracking'=>$shipment
        ]);
    }


    // GET /orders/{id}/invoice
    public function invoice($id)
    {
        $order = Order::with(['items.product','address'])
            ->findOrFail($id);

        return response()->json([
            'success'=>true,
            'invoice'=>$order
        ]);
    }

}