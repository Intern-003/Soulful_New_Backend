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
     * 🔄 AUTO SYNC ORDER STATUS FROM ITEMS
     */
    private function syncOrderStatus($orderId)
    {
        $items = OrderItem::where('order_id', $orderId)->pluck('status');

        if ($items->isEmpty()) return;

        $order = Order::find($orderId);

        if (!$order) return;

        // ✅ ALL DELIVERED
        if ($items->every(fn($s) => $s === 'delivered')) {
            $order->update(['order_status' => 'delivered']);
            return;
        }

        // ✅ ALL SHIPPED OR DELIVERED MIX
        if ($items->every(fn($s) => in_array($s, ['shipped', 'delivered']))) {
            $order->update(['order_status' => 'shipped']);
            return;
        }

        // ✅ DEFAULT
        $order->update(['order_status' => 'processing']);
    }

    /**
     * 📊 SUMMARY
     */
    public function summary(Request $request)
    {
        $user = $request->user();

        $query = OrderItem::where(function ($q) use ($user) {
            $q->where('vendor_id', optional($user->vendor)->id)
              ->orWhere('creator_id', $user->id);
        });

        return response()->json([
            'success' => true,
            'data' => [
                'total_orders' => (clone $query)->distinct('order_id')->count('order_id'),
                'total_revenue' => (clone $query)->sum('total'),
                'status_breakdown' => [
                    'pending' => (clone $query)->where('status', 'pending')->count(),
                    'processing' => (clone $query)->where('status', 'processing')->count(),
                    'shipped' => (clone $query)->where('status', 'shipped')->count(),
                    'delivered' => (clone $query)->where('status', 'delivered')->count(),
                ]
            ]
        ]);
    }

    /**
     * 📦 ORDERS LIST
     */
    public function orders(Request $request)
    {
        $user = $request->user();

        $orderIds = OrderItem::where(function ($q) use ($user) {
                $q->where('vendor_id', optional($user->vendor)->id)
                  ->orWhere('creator_id', $user->id);
            })
            ->distinct()
            ->pluck('order_id');

        $orders = Order::with([
            'address',
            'items' => function ($q) use ($user) {
                $q->where(function ($q2) use ($user) {
                    $q2->where('vendor_id', optional($user->vendor)->id)
                       ->orWhere('creator_id', $user->id);
                })->with(['product', 'vendor', 'shipment']);
            }
        ])
        ->whereIn('id', $orderIds)
        ->latest()
        ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * 🔍 ORDER DETAILS
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $order = Order::with([
            'address',
            'items' => function ($q) use ($user) {
                $q->where(function ($q2) use ($user) {
                    $q2->where('vendor_id', optional($user->vendor)->id)
                       ->orWhere('creator_id', $user->id);
                })->with(['product', 'vendor', 'shipment']);
            }
        ])
        ->whereHas('items', function ($q) use ($user) {
            $q->where(function ($q2) use ($user) {
                $q2->where('vendor_id', optional($user->vendor)->id)
                   ->orWhere('creator_id', $user->id);
            });
        })
        ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }

    /**
     * 🚚 CREATE SHIPMENT (FULL + ITEM WISE)
     */
    public function createShipment(Request $request, $id)
    {
        $user = $request->user();

        $request->validate([
            'carrier' => 'required|string',
            'tracking_number' => 'required|string'
        ]);

        $order = Order::findOrFail($id);

        // ✅ seller items
        $items = OrderItem::where('order_id', $id)
            ->where(function ($q) use ($user) {
                $q->where('vendor_id', optional($user->vendor)->id)
                  ->orWhere('creator_id', $user->id);
            })
            ->get();

        if ($items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $totalItems = OrderItem::where('order_id', $id)->count();

        $shipments = [];

        /**
         * =========================
         * CASE 1: FULL ORDER SHIPMENT
         * =========================
         */
        if ($items->count() === $totalItems) {

            $exists = Shipment::where('order_id', $id)
                ->whereNull('order_item_id')
                ->where('vendor_id', optional($user->vendor)->id)
                ->where('creator_id', $user->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shipment already exists'
                ], 400);
            }

            $shipments[] = Shipment::create([
                'order_id' => $id,
                'order_item_id' => null,
                'vendor_id' => optional($user->vendor)->id,
                'creator_id' => $user->id,
                'carrier' => $request->carrier,
                'tracking_number' => $request->tracking_number,
                'status' => 'shipped',
                'shipped_at' => now()
            ]);

            foreach ($items as $item) {
                $item->update(['status' => 'shipped']);
            }
        }

        /**
         * =========================
         * CASE 2: ITEM WISE SHIPMENT
         * =========================
         */
        else {
            foreach ($items as $item) {

                if ($item->shipment) continue;

                $shipments[] = Shipment::create([
                    'order_id' => $id,
                    'order_item_id' => $item->id,
                    'vendor_id' => $item->vendor_id,
                    'creator_id' => $item->creator_id,
                    'carrier' => $request->carrier,
                    'tracking_number' => $request->tracking_number,
                    'status' => 'shipped',
                    'shipped_at' => now()
                ]);

                $item->update(['status' => 'shipped']);
            }
        }

        $this->syncOrderStatus($id);

        return response()->json([
            'success' => true,
            'message' => 'Shipment created successfully',
            'data' => $shipments
        ], 201);
    }

    /**
     * 🧾 UPDATE ITEM STATUS
     */
    public function updateItemStatus(Request $request, $itemId)
    {
        $user = $request->user();

        $request->validate([
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled'
        ]);

        $item = OrderItem::where('id', $itemId)
            ->where(function ($q) use ($user) {
                $q->where('vendor_id', optional($user->vendor)->id)
                  ->orWhere('creator_id', $user->id);
            })
            ->firstOrFail();

        $order = Order::find($item->order_id);

        if ($order && $order->order_status === 'delivered') {
            return response()->json([
                'success' => false,
                'message' => 'Order already delivered'
            ], 400);
        }

        $item->update(['status' => $request->status]);

        $this->syncOrderStatus($item->order_id);

        return response()->json([
            'success' => true,
            'message' => 'Item status updated'
        ]);
    }
}