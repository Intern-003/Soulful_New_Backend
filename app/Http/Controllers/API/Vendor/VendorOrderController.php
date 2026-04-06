<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shipment;
use App\Models\Order;
use App\Models\OrderItem;

class VendorOrderController extends Controller
{

    /**
     * 🔹 SUMMARY (Dashboard stats)
     */
    public function summary(Request $request)
    {
        $vendor = $request->user()->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }

        $query = OrderItem::where(function ($q) use ($vendor) {
            $q->where('vendor_id', $vendor->id)
              ->orWhere('creator_id', $vendor->id);
        });

        return response()->json([
            'success' => true,
            'data' => [
                'total_orders' => (clone $query)->distinct('order_id')->count('order_id'),
                'total_revenue' => (clone $query)->sum('total'),
                'status_breakdown' => [
                    'pending' => (clone $query)->where('status', 'pending')->count(),
                    'processing' => (clone $query)->where('status', 'processing')->count(),
                    'delivered' => (clone $query)->where('status', 'delivered')->count(),
                ]
            ]
        ]);
    }


    /**
     * 🔹 ORDERS LIST (only vendor-related orders)
     */
    public function orders(Request $request)
    {
        $vendor = $request->user()->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }

        $orderIds = OrderItem::where(function ($q) use ($vendor) {
                $q->where('vendor_id', $vendor->id)
                  ->orWhere('creator_id', $vendor->id);
            })
            ->distinct()
            ->pluck('order_id');

        $orders = Order::with(['address'])
            ->whereIn('id', $orderIds)
            ->latest()
            ->paginate(10);

        // ✅ Filter items per order
        $orders->getCollection()->transform(function ($order) use ($vendor) {

            $items = $order->items()
                ->where(function ($q) use ($vendor) {
                    $q->where('vendor_id', $vendor->id)
                      ->orWhere('creator_id', $vendor->id);
                })
                ->with(['product', 'vendor'])
                ->get();

            $order->setRelation('items', $items);

            return $order;
        });

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }


    /**
     * 🔹 ORDER DETAILS (ONLY vendor items)
     */
    public function show(Request $request, $id)
    {
        $vendor = $request->user()->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }

        $order = Order::with(['address'])
            ->whereHas('items', function ($q) use ($vendor) {
                $q->where(function ($q2) use ($vendor) {
                    $q2->where('vendor_id', $vendor->id)
                       ->orWhere('creator_id', $vendor->id);
                });
            })
            ->findOrFail($id);

        // ✅ ONLY vendor's items
        $items = $order->items()
            ->where(function ($q) use ($vendor) {
                $q->where('vendor_id', $vendor->id)
                  ->orWhere('creator_id', $vendor->id);
            })
            ->with(['product', 'vendor'])
            ->get();

        $order->setRelation('items', $items);

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }


    /**
     * 🔹 CREATE SHIPMENT (secure)
     */
    public function createShipment(Request $request, $id)
    {
        $vendor = $request->user()->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }

        $request->validate([
            'carrier' => 'required|string',
            'tracking_number' => 'required|string'
        ]);

        $order = Order::findOrFail($id);

        // ✅ Ensure vendor owns at least one item
        $hasItems = OrderItem::where('order_id', $order->id)
            ->where(function ($q) use ($vendor) {
                $q->where('vendor_id', $vendor->id)
                  ->orWhere('creator_id', $vendor->id);
            })
            ->exists();

        if (!$hasItems) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized for this order'
            ], 403);
        }

        $shipment = Shipment::create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'carrier' => $request->carrier,
            'tracking_number' => $request->tracking_number,
            'status' => 'shipped',
            'shipped_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shipment created successfully',
            'data' => $shipment
        ], 201);
    }


    /**
     * 🔹 UPDATE ITEM STATUS
     */
    public function updateItemStatus(Request $request, $itemId)
    {
        $vendor = $request->user()->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }

        $request->validate([
            'status' => 'required|string'
        ]);

        $item = OrderItem::where('id', $itemId)
            ->where(function ($q) use ($vendor) {
                $q->where('vendor_id', $vendor->id)
                  ->orWhere('creator_id', $vendor->id);
            })
            ->firstOrFail();

        $item->update([
            'status' => $request->status
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Item status updated'
        ]);
    }
}