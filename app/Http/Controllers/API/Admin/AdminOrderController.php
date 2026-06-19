<?php
// app/Http/Controllers/API/Admin/AdminOrderController.php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Shipment;

class AdminOrderController extends Controller
{
    /**
     * Dashboard Summary - FIXED
     */
    /**
     * Dashboard Summary - FIXED with correct relationship names
     */
    public function summary(Request $request)
    {
        $query = Order::query();

        // Date range filter
        if ($request->from && $request->to) {
            $query->whereBetween('created_at', [$request->from, $request->to]);
        }

        $totalOrders = $query->count();
        $totalRevenue = $query->where('payment_status', 'paid')->sum('grand_total');
        $totalPendingPayment = $query->where('payment_status', 'pending')->sum('grand_total');

        // FIXED: Use select() + selectRaw() + pluck() correctly
        $statusCounts = Order::select('order_status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('order_status')
            ->pluck('count', 'order_status')
            ->toArray();

        $paymentStatusCounts = Order::select('payment_status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('payment_status')
            ->pluck('count', 'payment_status')
            ->toArray();

        // Today's stats
        $todayOrders = Order::whereDate('created_at', today())->count();
        $todayRevenue = Order::whereDate('created_at', today())
            ->where('payment_status', 'paid')
            ->sum('grand_total');

        // FIXED: Use correct relationship name 'items' instead of 'orderItems'
        // Also use the direct relationship from Vendor to OrderItem if needed
        $vendorStats = Vendor::with([
            'orderItems' => function ($q) {
                $q->where('settlement_status', 'pending');
            }
        ])
            ->get(['id', 'store_name'])
            ->map(function ($vendor) {
                $vendor->pending_settlement = $vendor->orderItems->sum('vendor_payout');
                return $vendor;
            });

        return response()->json([
            'success' => true,
            'data' => [
                'total_orders' => $totalOrders,
                'total_revenue' => round($totalRevenue, 2),
                'total_pending_payment' => round($totalPendingPayment, 2),
                'status_breakdown' => $statusCounts,
                'payment_status_breakdown' => $paymentStatusCounts,
                'today' => [
                    'orders' => $todayOrders,
                    'revenue' => round($todayRevenue, 2)
                ],
                'vendor_pending_settlement' => $vendorStats
            ]
        ]);
    }
    /**
     * List all orders (Admin)
     */
    public function index(Request $request)
    {
        $query = Order::with([
            'user:id,name,email',
            'items.product',
            'items.vendor:id,store_name',
            'address'
        ]);

        // Filters - Use 'order_status' column
        if ($request->status) {
            $query->where('order_status', $request->status);
        }

        if ($request->payment_status) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->settlement_status) {
            $query->where('settlement_status', $request->settlement_status);
        }

        if ($request->vendor_id) {
            $query->whereHas('items', function ($q) use ($request) {
                $q->where('vendor_id', $request->vendor_id);
            });
        }

        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $orders = $query->latest()->paginate(15);

        foreach ($orders as $order) {
            $order->total_items = $order->items->sum('quantity');
            $order->total_commission = $order->items->sum('commission_amount');
            $order->total_vendor_payout = $order->items->sum('vendor_payout');
        }

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * Get single order details
     */
    public function show($id)
    {
        $order = Order::with([
            'user:id,name,email,phone',
            'items.product',
            'items.product.images',
            'items.variant',
            'items.vendor:id,store_name',
            'address',
            'payments',
            'shipments',
            'statusHistory'
        ])->findOrFail($id);

        // Group items by vendor
        $itemsByVendor = $order->items->groupBy('vendor_id');

        // Calculate totals
        $totals = [
            'subtotal' => $order->subtotal,
            'coupon_discount' => $order->discount,
            'platform_discount' => $order->platform_coupon_discount,
            'vendor_discount' => $order->vendor_coupon_discount,
            'tax_total' => $order->tax,
            'shipping_total' => $order->shipping_total,
            'grand_total' => $order->grand_total,
            'total_commission' => $order->items->sum('commission_amount'),
            'total_vendor_payout' => $order->items->sum('vendor_payout')
        ];

        // Vendor breakdown
        $vendorBreakdown = [];
        foreach ($itemsByVendor as $vendorId => $items) {
            $firstItem = $items->first();
            $vendor = $firstItem ? $firstItem->vendor : null;
            $vendorBreakdown[] = [
                'vendor_id' => $vendorId,
                'vendor_name' => $vendor ? $vendor->store_name : 'Unknown',
                'items_count' => $items->count(),
                'subtotal' => $items->sum(function ($i) {
                    return ($i->selling_price ?? $i->price) * $i->quantity;
                }),
                'commission' => $items->sum('commission_amount'),
                'vendor_payout' => $items->sum('vendor_payout'),
                'settlement_status' => $firstItem ? $firstItem->settlement_status : 'pending'
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order' => $order,
                'items_by_vendor' => $itemsByVendor,
                'totals' => $totals,
                'vendor_breakdown' => $vendorBreakdown
            ]
        ]);
    }

    /**
     * Update order status
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:pending,processing,confirmed,shipped,delivered,cancelled',
            'note' => 'nullable|string'
        ]);

        $order = Order::findOrFail($id);
        
        // Use 'order_status' column
        $oldStatus = $order->order_status;

        $order->update([
            'order_status' => $request->status,
            'shipped_at' => $request->status === 'shipped' && !$order->shipped_at ? now() : $order->shipped_at,
            'delivered_at' => $request->status === 'delivered' && !$order->delivered_at ? now() : $order->delivered_at
        ]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => $request->status,
            'note' => $request->note ?? "Order status updated from {$oldStatus} to {$request->status} by admin"
        ]);

        // If order is delivered, mark items for settlement
        if ($request->status === 'delivered') {
            OrderItem::where('order_id', $order->id)->update([
                'settlement_status' => 'pending',
                'eligible_for_settlement_at' => now()->addDays(7)
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully',
            'data' => [
                'old_status' => $oldStatus,
                'new_status' => $request->status
            ]
        ]);
    }

    /**
     * Update payment status
     */
    public function updatePaymentStatus(Request $request, $id)
    {
        $request->validate([
            'payment_status' => 'required|in:pending,paid,failed,refunded',
            'payment_id' => 'nullable|string',
            'note' => 'nullable|string'
        ]);

        $order = Order::findOrFail($id);
        $oldStatus = $order->payment_status;

        $order->update([
            'payment_status' => $request->payment_status,
            'payment_id' => $request->payment_id ?? $order->payment_id,
            'paid_at' => $request->payment_status === 'paid' ? now() : $order->paid_at
        ]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => 'payment_' . $request->payment_status,
            'note' => $request->note ?? "Payment status updated from {$oldStatus} to {$request->payment_status}"
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment status updated successfully'
        ]);
    }

    /**
     * Get revenue report by date range
     */
    public function revenueReport(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'group_by' => 'nullable|in:day,month,year'
        ]);

        $groupBy = $request->group_by ?? 'day';
        $format = $groupBy === 'day' ? '%Y-%m-%d' : ($groupBy === 'month' ? '%Y-%m' : '%Y');

        $revenue = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$request->from, $request->to])
            ->selectRaw("DATE_FORMAT(created_at, '{$format}') as period, 
                     COUNT(*) as orders_count,
                     SUM(grand_total) as revenue,
                     SUM(tax) as tax,
                     SUM(shipping_total) as shipping,
                     SUM(discount + platform_coupon_discount + vendor_coupon_discount) as discounts")
            ->groupBy('period')
            ->get();

        foreach ($revenue as $item) {
            $item->commission = OrderItem::whereHas('order', function ($q) use ($request, $item) {
                $q->where('payment_status', 'paid')
                    ->whereBetween('created_at', [$request->from, $request->to]);
            })->whereMonth('created_at', 'like', $item->period . '%')
                ->sum('commission_amount');
        }

        $vendorRevenue = OrderItem::whereHas('order', function ($q) use ($request) {
            $q->where('payment_status', 'paid')
                ->whereBetween('created_at', [$request->from, $request->to]);
        })
            ->selectRaw('vendor_id, SUM(vendor_payout) as total_payout, SUM(commission_amount) as total_commission, COUNT(DISTINCT order_id) as orders_count')
            ->with('vendor:id,store_name')
            ->groupBy('vendor_id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'from' => $request->from,
                    'to' => $request->to,
                    'group_by' => $groupBy
                ],
                'summary' => [
                    'total_revenue' => $revenue->sum('revenue'),
                    'total_orders' => $revenue->sum('orders_count'),
                    'total_tax' => $revenue->sum('tax'),
                    'total_shipping' => $revenue->sum('shipping'),
                    'total_discounts' => $revenue->sum('discounts'),
                    'total_commission' => $revenue->sum('commission')
                ],
                'breakdown' => $revenue,
                'vendor_breakdown' => $vendorRevenue
            ]
        ]);
    }

    /**
     * Get order status timeline for analytics
     */
    public function statusTimeline(Request $request)
    {
        $request->validate([
            'days' => 'nullable|integer|min:1|max:90'
        ]);

        $days = $request->days ?? 30;
        $startDate = now()->subDays($days);

        $statusHistory = OrderStatusHistory::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, status, COUNT(*) as count')
            ->groupBy('date', 'status')
            ->orderBy('date')
            ->get()
            ->groupBy('date');

        return response()->json([
            'success' => true,
            'data' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
                'timeline' => $statusHistory
            ]
        ]);
    }

    /**
     * Export orders (CSV/Excel)
     */
    public function export(Request $request)
    {
        $request->validate([
            'format' => 'required|in:csv,excel',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'status' => 'nullable|string'
        ]);

        $query = Order::with(['user', 'items.vendor']);

        if ($request->from) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('created_at', '<=', $request->to);
        }
        if ($request->status) {
            $query->where('order_status', $request->status);
        }

        $orders = $query->get();

        $exportData = $orders->map(function ($order) {
            return [
                'Order Number' => $order->order_number,
                'Customer' => $order->user->name ?? 'N/A',
                'Email' => $order->user->email ?? 'N/A',
                'Date' => $order->created_at->format('Y-m-d H:i:s'),
                'Status' => $order->order_status,
                'Payment Status' => $order->payment_status,
                'Subtotal' => $order->subtotal,
                'Discount' => $order->discount + $order->platform_coupon_discount + $order->vendor_coupon_discount,
                'Tax' => $order->tax,
                'Shipping' => $order->shipping_total,
                'Total' => $order->grand_total,
                'Items Count' => $order->items->count(),
                'Total Commission' => $order->items->sum('commission_amount'),
                'Total Vendor Payout' => $order->items->sum('vendor_payout')
            ];
        });

        if ($request->format === 'csv') {
            $csv = $this->arrayToCsv($exportData->toArray());
            return response($csv, 200)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="orders_export_' . now()->format('Ymd_His') . '.csv"');
        }

        return response()->json([
            'success' => true,
            'data' => $exportData
        ]);
    }

    /**
     * Convert array to CSV
     */
    public function arrayToCsv($data)
    {
        $output = fopen('php://temp', 'r+');
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
        rewind($output);
        return stream_get_contents($output);
    }



    


    /**
     * Get items that need marketplace shipment
     */
    public function getMarketplaceItems(Request $request)
    {
        $query = OrderItem::with(['order', 'product', 'vendor'])
            ->where('shipping_mode', 'marketplace') // ✅ Existing column
            ->whereNull('shipment_id') // ✅ Existing column
            ->where('settlement_status', 'pending');
            
        if ($request->has('order_id')) {
            $query->where('order_id', $request->order_id);
        }
        
        $items = $query->latest()->paginate(20);
        
        // Group by order for better display
        $groupedByOrder = $items->groupBy('order_id');
        
        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'grouped_by_order' => $groupedByOrder
            ]
        ]);
    }
    
    /**
     * Admin creates marketplace shipment
     */
    public function createMarketplaceShipment(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'carrier' => 'required|string',
            'tracking_number' => 'required|string',
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'exists:order_items,id',
            'courier_cost' => 'nullable|numeric|min:0',
            'estimated_delivery' => 'nullable|date'
        ]);
        
        $order = Order::findOrFail($request->order_id);
        
        // ✅ Only marketplace items without shipment
        $items = OrderItem::whereIn('id', $request->item_ids)
            ->where('order_id', $request->order_id)
            ->where('shipping_mode', 'marketplace') // ✅ Existing column
            ->whereNull('shipment_id') // ✅ Existing column
            ->get();
            
        if ($items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No valid marketplace items found for shipment'
            ], 404);
        }
        
        DB::transaction(function () use ($order, $items, $request) {
            // ✅ Create marketplace shipment (vendor_id = null)
            $shipment = Shipment::create([
                'order_id' => $order->id,
                'vendor_id' => null, // ✅ Marketplace shipment
                'carrier' => $request->carrier,
                'tracking_number' => $request->tracking_number,
                'status' => 'shipped',
                'shipped_at' => now(),
                'estimated_delivery' => $request->estimated_delivery,
                'shipping_mode' => 'marketplace', // ✅ Existing column
                'shipping_cost' => $items->sum('shipping_charge'),
                'courier_cost' => $request->courier_cost ?? 0
            ]);
            
            // ✅ Link items to shipment
            foreach ($items as $item) {
                $item->update(['shipment_id' => $shipment->id]);
            }
            
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'shipped',
                'note' => "Marketplace items shipped via {$request->carrier}. Tracking: {$request->tracking_number}"
            ]);
            
            // ✅ Sync order status
            $this->syncOrderStatus($order->id);
        });
        
        return response()->json([
            'success' => true,
            'message' => 'Marketplace shipment created successfully',
            'data' => [
                'items_count' => $items->count(),
                'carrier' => $request->carrier,
                'tracking_number' => $request->tracking_number
            ]
        ], 201);
    }
    
    /**
     * Get order shipment details
     */
    public function getOrderShipments($orderId)
    {
        $order = Order::with([
            'shipments' => function($q) {
                $q->with(['items.product', 'vendor']);
            },
            'items' => function($q) {
                $q->whereNull('shipment_id')
                    ->select('id', 'order_id', 'product_name', 'shipping_mode', 'vendor_id');
            }
        ])->findOrFail($orderId);
        
        // ✅ Separate shipments by mode using existing column
        $vendorShipments = $order->shipments->filter(function($s) {
            return $s->shipping_mode === 'vendor' && $s->vendor_id !== null;
        });
        
        $marketplaceShipments = $order->shipments->filter(function($s) {
            return $s->shipping_mode === 'marketplace' && $s->vendor_id === null;
        });
        
        // Items without shipment (group by shipping_mode)
        $pendingItems = $order->items->filter(function($item) {
            return $item->shipment_id === null;
        })->groupBy('shipping_mode');
        
        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'vendor_shipments' => $vendorShipments->values(),
                'marketplace_shipments' => $marketplaceShipments->values(),
                'pending_vendor_items' => $pendingItems->get('vendor', collect()),
                'pending_marketplace_items' => $pendingItems->get('marketplace', collect()),
                'total_items' => $order->items->count(),
                'shipped_items' => $order->items->whereNotNull('shipment_id')->count()
            ]
        ]);
    }
    
    /**
     * Update shipment status (Admin)
     */
    public function updateShipmentStatus(Request $request, $shipmentId)
    {
        $request->validate([
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled'
        ]);
        
        $shipment = Shipment::findOrFail($shipmentId);
        $oldStatus = $shipment->status;
        
        $shipment->update([
            'status' => $request->status,
            'shipped_at' => $request->status === 'shipped' ? now() : $shipment->shipped_at,
            'delivered_at' => $request->status === 'delivered' ? now() : $shipment->delivered_at
        ]);
        
        // ✅ No need to update order_items - shipment status is enough
        
        $this->syncOrderStatus($shipment->order_id);
        
        return response()->json([
            'success' => true,
            'message' => 'Shipment status updated successfully',
            'data' => $shipment
        ]);
    }
    
    /**
     * Sync order status based on shipments
     * ✅ Uses existing shipment status, no new columns needed
     */
    /**
 * Sync order status based on shipments
 */
private function syncOrderStatus($orderId)
{
    $order = Order::find($orderId);
    if (!$order) return;
    
    $items = OrderItem::where('order_id', $orderId)->get();
    
    if ($items->isEmpty()) return;
    
    // ✅ Check if all items have shipments and are delivered
    $allDelivered = $items->every(function($item) {
        if ($item->shipment_id === null) return false;
        $shipment = Shipment::find($item->shipment_id);
        return $shipment && $shipment->status === 'delivered';
    });
    
    if ($allDelivered) {
        $order->update([
            'order_status' => 'delivered',
            'delivered_at' => now()
        ]);
        return;
    }
    
    // ✅ Check if any items have shipped shipments
    $anyShipped = $items->some(function($item) {
        if ($item->shipment_id === null) return false;
        $shipment = Shipment::find($item->shipment_id);
        return $shipment && in_array($shipment->status, ['shipped', 'delivered']);
    });
    
    if ($anyShipped && $order->order_status !== 'shipped') {
        $order->update([
            'order_status' => 'shipped',
            'shipped_at' => now()
        ]);
        return;
    }
    
    // ✅ If no items are shipped but order is shipped, keep it
    // This handles edge cases where order was marked shipped manually
}

}