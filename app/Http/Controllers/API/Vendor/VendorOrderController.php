<?php
// app/Http/Controllers/API/Vendor/VendorOrderController.php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shipment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;

class VendorOrderController extends Controller
{
    /**
     * Helper: Verify order item belongs to vendor
     */
    private function verifyItemOwnership($itemId, $vendorId)
    {
        return OrderItem::where('id', $itemId)
            ->where('vendor_id', $vendorId)
            ->exists();
    }

    /**
     * Helper: Verify all items belong to vendor
     */
    private function verifyAllItemsBelongToVendor($itemIds, $vendorId)
    {
        $count = OrderItem::whereIn('id', $itemIds)
            ->where('vendor_id', $vendorId)
            ->count();
        return $count === count($itemIds);
    }

    /**
     * Helper: Check if vendor owns all items in order
     */
    private function vendorOwnsAllItemsInOrder($orderId, $vendorId)
    {
        $totalItems = OrderItem::where('order_id', $orderId)->count();
        $vendorItems = OrderItem::where('order_id', $orderId)
            ->where('vendor_id', $vendorId)
            ->count();
        return $totalItems === $vendorItems && $totalItems > 0;
    }

    /**
     * Get vendor order summary (ONLY vendor's own data)
     */
    public function summary(Request $request)
    {
        $user = $request->user();
        $vendorId = optional($user->vendor)->id;

        if (!$vendorId) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }

        $query = OrderItem::where('vendor_id', $vendorId);

        $totalRevenue = $query->sum('vendor_payout');
        $totalCommission = $query->sum('commission_amount');
        $uniqueOrderIds = $query->distinct('order_id')->pluck('order_id');

        $statusBreakdown = [
            'pending' => $query->whereNull('shipment_id')->count(),
            'processing' => $query->whereHas('shipment', fn($q) => $q->where('status', 'processing'))->count(),
            'shipped' => $query->whereHas('shipment', fn($q) => $q->where('status', 'shipped'))->count(),
            'delivered' => $query->whereHas('shipment', fn($q) => $q->where('status', 'delivered'))->count(),
            'cancelled' => $query->whereHas('shipment', fn($q) => $q->where('status', 'cancelled'))->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'total_orders' => $uniqueOrderIds->count(),
                'total_items_sold' => $query->sum('quantity'),
                'total_revenue' => round($totalRevenue, 2),
                'total_commission' => round($totalCommission, 2),
                'pending_settlement' => round($query->where('settlement_status', 'pending')->sum('vendor_payout'), 2),
                'settled_amount' => round($query->where('settlement_status', 'settled')->sum('vendor_payout'), 2),
                'status_breakdown' => $statusBreakdown
            ]
        ]);
    }

    /**
     * Get vendor orders list (ONLY orders containing vendor's items)
     */
    public function orders(Request $request)
    {
        $user = $request->user();
        $vendorId = optional($user->vendor)->id;

        if (!$vendorId) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }

        $orderIds = OrderItem::where('vendor_id', $vendorId)
            ->distinct()
            ->pluck('order_id');

        // ✅ FIXED: Don't select 'images' column from products table
        $orders = Order::with([
            'address',
            'user:id,name,email,phone',
            'items' => function ($q) use ($vendorId) {
                $q->where('vendor_id', $vendorId)
                    ->with([
                        'product' => function ($pq) {
                            $pq->select('id', 'name', 'slug', 'price', 'discount_price'); // ✅ Only select existing columns
                        },
                        'product.images', // ✅ Load images through relationship
                        'variant',
                        'shipment'
                    ]);
            },
            'shipments' => function ($q) use ($vendorId) {
                $q->where('vendor_id', $vendorId);
            }
        ])
            ->whereIn('id', $orderIds)
            ->latest()
            ->paginate(10);

        $orders->getCollection()->transform(function ($order) use ($vendorId) {
            $vendorItems = $order->items->where('vendor_id', $vendorId);
            $vendorShipments = $order->shipments->where('vendor_id', $vendorId);

            $order->vendor_subtotal = $vendorItems->sum(fn($i) => ($i->selling_price ?? $i->price) * $i->quantity);
            $order->vendor_payout = $vendorItems->sum('vendor_payout');
            $order->vendor_items_count = $vendorItems->count();
            $order->vendor_shipment_status = $vendorShipments->first()->status ?? 'pending';
            $order->is_full_order_vendor = $this->vendorOwnsAllItemsInOrder($order->id, $vendorId);

            return $order;
        });

        return response()->json([
            'success' => true,
            'data' => $orders,
            'message' => 'Showing only your orders and items'
        ]);
    }

    /**
     * Get single order details for vendor (ONLY vendor's items)
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $vendorId = optional($user->vendor)->id;

        if (!$vendorId) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }

        $hasVendorItems = OrderItem::where('order_id', $id)
            ->where('vendor_id', $vendorId)
            ->exists();

        if (!$hasVendorItems) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - This order does not contain your items'
            ], 403);
        }

        // ✅ FIXED: Don't select 'images' column from products table
        $order = Order::with([
            'address',
            'user:id,name,email,phone',
            'items' => function ($q) use ($vendorId) {
                $q->where('vendor_id', $vendorId)
                    ->with([
                        'product' => function ($pq) {
                            $pq->select('id', 'name', 'slug', 'price', 'discount_price', 'sku');
                        },
                        'product.images', // ✅ Load images through relationship
                        'variant',
                        'shipment'
                    ]);
            },
            'shipments' => function ($q) use ($vendorId) {
                $q->where('vendor_id', $vendorId);
            }
        ])
            ->findOrFail($id);

        $vendorItems = $order->items;
        $vendorTotals = [
            'subtotal' => $vendorItems->sum(fn($i) => ($i->selling_price ?? $i->price) * $i->quantity),
            'coupon_discount' => $vendorItems->sum('vendor_coupon_share'),
            'tax_total' => $vendorItems->sum('tax_amount'),
            'shipping_total' => $vendorItems->sum('shipping_charge'),
            'commission_total' => $vendorItems->sum('commission_amount'),
            'vendor_payout' => $vendorItems->sum('vendor_payout')
        ];

        $hasOtherVendors = OrderItem::where('order_id', $id)
            ->where('vendor_id', '!=', $vendorId)
            ->exists();

        return response()->json([
            'success' => true,
            'data' => [
                'order' => $order,
                'vendor_totals' => $vendorTotals,
                'other_vendors_in_order' => $hasOtherVendors,
                'is_full_order_vendor' => $this->vendorOwnsAllItemsInOrder($id, $vendorId),
                'note' => 'Only your items are shown. Other vendors\' data is hidden for privacy.'
            ]
        ]);
    }

    /**
     * Get vendor's items for a specific order (ONLY their items)
     */
    public function getVendorOrderItems(Request $request, $orderId)
    {
        $user = $request->user();
        $vendorId = optional($user->vendor)->id;

        if (!$vendorId) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }

        // ✅ FIXED: Don't select 'images' column from products table
        $items = OrderItem::where('order_id', $orderId)
            ->where('vendor_id', $vendorId)
            ->with([
                'product' => function ($q) {
                    $q->select('id', 'name', 'slug', 'price', 'discount_price');
                },
                'product.images', // ✅ Load images through relationship
                'variant',
                'shipment'
            ])
            ->get();

        if ($items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No items found for your store in this order'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $orderId,
                'vendor_id' => $vendorId,
                'items' => $items,
                'total_amount' => $items->sum('vendor_payout'),
                'items_count' => $items->count()
            ]
        ]);
    }

    /**
     * Create shipment for vendor's order items
     */

    public function createShipment(Request $request, $id)
    {
        $user = $request->user();
        $vendorId = optional($user->vendor)->id;

        if (!$vendorId) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }

        $request->validate([
            'carrier' => 'required|string',
            'tracking_number' => 'required|string',
            'estimated_delivery' => 'nullable|date',
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'exists:order_items,id'
        ]);

        $order = Order::findOrFail($id);

        // Get vendor's items (only those without shipment)
        $query = OrderItem::where('order_id', $id)
            ->where('vendor_id', $vendorId)
            ->whereNull('shipment_id'); // ✅ Only items not yet shipped

        if ($request->has('item_ids')) {
            if (!$this->verifyAllItemsBelongToVendor($request->item_ids, $vendorId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'One or more items do not belong to your store'
                ], 403);
            }
            $items = $query->whereIn('id', $request->item_ids)->get();
        } else {
            $items = $query->get();
        }

        if ($items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No items found for shipment'
            ], 404);
        }

        $isFullOrderShipment = $this->vendorOwnsAllItemsInOrder($id, $vendorId);

        DB::transaction(function () use ($order, $items, $request, $vendorId, $isFullOrderShipment) {
            // ✅ Create shipment using existing columns
            $shipment = Shipment::create([
                'order_id' => $order->id,
                'vendor_id' => $vendorId, // ✅ Vendor shipment
                'carrier' => $request->carrier,
                'tracking_number' => $request->tracking_number,
                'status' => 'shipped',
                'shipped_at' => now(),
                'estimated_delivery' => $request->estimated_delivery,
                'shipping_mode' => 'vendor', // ✅ Existing column
                'shipping_cost' => $items->sum('shipping_charge'),
                'courier_cost' => 0
            ]);

            // ✅ Link items to shipment (existing shipment_id column)
            foreach ($items as $item) {
                $item->update(['shipment_id' => $shipment->id]);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'shipped',
                'note' => $isFullOrderShipment
                    ? "Full order shipped via {$request->carrier}. Tracking: {$request->tracking_number}"
                    : "{$items->count()} item(s) shipped via {$request->carrier}. Tracking: {$request->tracking_number}"
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Shipment created successfully',
            'data' => [
                'items_count' => $items->count(),
                'carrier' => $request->carrier,
                'tracking_number' => $request->tracking_number
            ]
        ], 201);
    }
    /**
     * Update individual item shipment status
     */
    public function updateItemStatus(Request $request, $itemId)
    {
        $user = $request->user();
        $vendorId = optional($user->vendor)->id;

        if (!$vendorId) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }

        if (!$this->verifyItemOwnership($itemId, $vendorId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - This item does not belong to your store'
            ], 403);
        }

        $request->validate([
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled',
            'carrier' => 'required_if:status,shipped|nullable|string',
            'tracking_number' => 'required_if:status,shipped|nullable|string',
        ]);

        $item = OrderItem::where('id', $itemId)
            ->where('vendor_id', $vendorId)
            ->with('shipment')
            ->firstOrFail();

        $shipment = $item->shipment;

        if (!$shipment) {
            $shipment = Shipment::create([
                'order_id' => $item->order_id,
                'vendor_id' => $vendorId,
                'carrier' => $request->carrier ?? 'Pending',
                'tracking_number' => $request->tracking_number ?? 'Pending',
                'status' => $request->status,
                'shipped_at' => $request->status === 'shipped' ? now() : null,
                'delivered_at' => $request->status === 'delivered' ? now() : null,
                'shipping_mode' => $item->shipping_mode ?? 'vendor',
                'shipping_cost' => $item->shipping_charge ?? 0,
                'courier_cost' => 0
            ]);
        } else {
            $oldStatus = $shipment->status;

            $validTransitions = [
                'pending' => ['processing', 'cancelled'],
                'processing' => ['shipped', 'cancelled'],
                'shipped' => ['delivered'],
                'delivered' => [],
                'cancelled' => []
            ];

            if (!in_array($request->status, $validTransitions[$oldStatus] ?? [])) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot transition from {$oldStatus} to {$request->status}"
                ], 400);
            }

            $updateData = [
                'status' => $request->status,
                'shipped_at' => $request->status === 'shipped' ? now() : $shipment->shipped_at,
                'delivered_at' => $request->status === 'delivered' ? now() : $shipment->delivered_at
            ];

            if ($request->has('carrier')) {
                $updateData['carrier'] = $request->carrier;
            }
            if ($request->has('tracking_number')) {
                $updateData['tracking_number'] = $request->tracking_number;
            }

            $shipment->update($updateData);
        }

        if (!$item->shipment_id && $shipment) {
            $item->update(['shipment_id' => $shipment->id]);
        }

        OrderStatusHistory::create([
            'order_id' => $item->order_id,
            'order_item_id' => $item->id,
            'status' => $request->status,
            'note' => "Item status updated to {$request->status} by vendor"
        ]);

        if ($request->status === 'delivered') {
            $item->update([
                'settlement_status' => 'pending',
                'eligible_for_settlement_at' => now()->addDays(7)
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Item status updated successfully',
            'data' => [
                'item_id' => $item->id,
                'new_status' => $request->status,
                'shipment_id' => $shipment->id
            ]
        ]);
    }

    /**
     * Bulk update multiple items status
     */
    public function bulkUpdateStatus(Request $request)
    {
        $user = $request->user();
        $vendorId = optional($user->vendor)->id;

        if (!$vendorId) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }

        $request->validate([
            'item_ids' => 'required|array',
            'item_ids.*' => 'exists:order_items,id',
            'status' => 'required|in:processing,shipped,delivered,cancelled',
            'carrier' => 'required_if:status,shipped|nullable|string',
            'tracking_number' => 'required_if:status,shipped|nullable|string',
        ]);

        if (!$this->verifyAllItemsBelongToVendor($request->item_ids, $vendorId)) {
            return response()->json([
                'success' => false,
                'message' => 'One or more items do not belong to your store'
            ], 403);
        }

        $items = OrderItem::whereIn('id', $request->item_ids)
            ->where('vendor_id', $vendorId)
            ->with('shipment')
            ->get();

        if ($items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No valid items found'
            ], 404);
        }

        DB::transaction(function () use ($items, $request, $vendorId) {
            foreach ($items as $item) {
                $shipment = $item->shipment;

                if (!$shipment) {
                    $shipment = Shipment::create([
                        'order_id' => $item->order_id,
                        'vendor_id' => $vendorId,
                        'carrier' => $request->carrier ?? 'Pending',
                        'tracking_number' => $request->tracking_number ?? 'Pending',
                        'status' => $request->status,
                        'shipped_at' => $request->status === 'shipped' ? now() : null,
                        'delivered_at' => $request->status === 'delivered' ? now() : null,
                        'shipping_mode' => $item->shipping_mode ?? 'vendor',
                        'shipping_cost' => $item->shipping_charge ?? 0,
                        'courier_cost' => 0
                    ]);
                } else {
                    $updateData = [
                        'status' => $request->status,
                        'shipped_at' => $request->status === 'shipped' ? now() : $shipment->shipped_at,
                        'delivered_at' => $request->status === 'delivered' ? now() : $shipment->delivered_at
                    ];

                    if ($request->has('carrier')) {
                        $updateData['carrier'] = $request->carrier;
                    }
                    if ($request->has('tracking_number')) {
                        $updateData['tracking_number'] = $request->tracking_number;
                    }

                    $shipment->update($updateData);
                }

                if (!$item->shipment_id && $shipment) {
                    $item->update(['shipment_id' => $shipment->id]);
                }

                OrderStatusHistory::create([
                    'order_id' => $item->order_id,
                    'order_item_id' => $item->id,
                    'status' => $request->status,
                    'note' => "Bulk update: Status changed to {$request->status}"
                ]);

                if ($request->status === 'delivered') {
                    $item->update([
                        'settlement_status' => 'pending',
                        'eligible_for_settlement_at' => now()->addDays(7)
                    ]);
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => count($items) . ' items updated successfully'
        ]);
    }

    /**
     * Get all shipments for vendor
     */
    public function shipments(Request $request)
    {
        $user = $request->user();
        $vendorId = optional($user->vendor)->id;

        if (!$vendorId) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }

        $shipments = Shipment::where('vendor_id', $vendorId)
            ->with(['order:id,order_number', 'items.product:id,name'])
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $shipments
        ]);
    }

    /**
     * Update shipment tracking information
     */
    public function updateShipment(Request $request, $shipmentId)
    {
        $user = $request->user();
        $vendorId = optional($user->vendor)->id;

        if (!$vendorId) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }

        $request->validate([
            'carrier' => 'nullable|string',
            'tracking_number' => 'nullable|string',
            'courier_cost' => 'nullable|numeric|min:0'
        ]);

        $shipment = Shipment::where('id', $shipmentId)
            ->where('vendor_id', $vendorId)
            ->firstOrFail();

        $shipment->update($request->only(['carrier', 'tracking_number', 'courier_cost']));

        return response()->json([
            'success' => true,
            'message' => 'Shipment updated successfully',
            'data' => $shipment
        ]);
    }
}