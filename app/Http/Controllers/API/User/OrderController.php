<?php
// app/Http/Controllers/API/User/OrderController.php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\VendorWallet;
use App\Models\VendorTransaction;
use App\Services\TaxService;
use App\Services\CommissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    protected $taxService;
    protected $commissionService;

    public function __construct(TaxService $taxService, CommissionService $commissionService)
    {
        $this->taxService = $taxService;
        $this->commissionService = $commissionService;
    }

    /**
     * GET /orders - List user orders
     */
    public function index(Request $request)
{
    $orders = Order::with([
        'items.product', 
        'items.product.images', 
        'items.vendor',
        'address'
    ])
    ->where('user_id', $request->user()->id)
    ->latest()
    ->paginate(10);

    // Add calculated fields for each order
    $orders->getCollection()->transform(function ($order) {
        $order->items_count = $order->items->count();
        // ✅ Change $order->status to $order->order_status
        $order->can_cancel = in_array($order->order_status, ['pending', 'processing']);
        $order->can_return = $order->order_status === 'delivered' && !$order->return_requested;
        return $order;
    });

    return response()->json([
        'success' => true,
        'data' => $orders
    ]);
}

    /**
     * GET /orders/{id} - Get single order details
     */
    public function show(Request $request, $id)
{
    $order = Order::with([
        'items.product',
        'items.product.images',
        'items.variant',
        'items.vendor',
        'address',
        'payments',
        'shipments',
        'statusHistory'
    ])
    ->where('user_id', $request->user()->id)
    ->findOrFail($id);

    // Calculate order summary
    $orderSummary = $this->calculateOrderSummary($order);

    // ✅ Add status alias for response
    $order->status = $order->order_status;

    return response()->json([
        'success' => true,
        'data' => [
            'order' => $order,
            'summary' => $orderSummary
        ]
    ]);
}

    /**
     * GET /orders/{id}/track - Track order status
     */
    /**
 * GET /orders/{id}/track - Track order status
 */
public function track(Request $request, $id)
{
    // ✅ Change 'status' to 'order_status'
    $order = Order::select('id', 'order_number', 'order_status', 'payment_status', 'shipped_at', 'delivered_at')
        ->where('user_id', $request->user()->id)
        ->findOrFail($id);

    // Get timeline
    $timeline = $this->getOrderTimeline($order);

    return response()->json([
        'success' => true,
        'data' => [
            'order_number' => $order->order_number,
            'status' => $order->order_status,  // ✅ Use order_status
            'payment_status' => $order->payment_status,
            'timeline' => $timeline,
            'estimated_delivery' => $this->calculateEstimatedDelivery($order)
        ]
    ]);
}

    /**
     * GET /orders/{id}/shipments - Get shipments
     */
    public function shipment(Request $request, $id)
    {
        $order = Order::with(['shipments' => function($q) {
            $q->with(['items']);
        }])
        ->where('user_id', $request->user()->id)
        ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'order_number' => $order->order_number,
                'shipments' => $order->shipments->map(function($shipment) {
                    return [
                        'id' => $shipment->id,
                        'carrier' => $shipment->carrier,
                        'tracking_number' => $shipment->tracking_number,
                        'status' => $shipment->status,
                        'shipped_at' => $shipment->shipped_at,
                        'estimated_delivery' => $shipment->estimated_delivery,
                        'items' => $shipment->items->map(function($item) {
                            return [
                                'product_name' => $item->product_name,
                                'quantity' => $item->quantity
                            ];
                        })
                    ];
                })
            ]
        ]);
    }

    /**
     * GET /orders/{id}/invoice - Generate invoice
     */
    public function invoice(Request $request, $id)
    {
        $order = Order::with([
            'items.product',
            'items.product.images',
            'items.vendor',
            'address',
            'user'
        ])
        ->where('user_id', $request->user()->id)
        ->findOrFail($id);

        // Group items by vendor for invoice display
        $itemsByVendor = $order->items->groupBy('vendor_id');

        return response()->json([
            'success' => true,
            'data' => [
                'invoice_number' => 'INV-' . $order->order_number,
                'order_number' => $order->order_number,
                'date' => $order->created_at->format('Y-m-d H:i:s'),
                'customer' => [
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                    'phone' => $order->user->phone ?? null
                ],
                'shipping_address' => $order->shipping_address,
                'billing_address' => $order->billing_address ?? $order->shipping_address,
                'items_by_vendor' => $itemsByVendor->map(function($items, $vendorId) {
                    $vendor = $items->first()->vendor;
                    return [
                        'vendor_name' => $vendor ? $vendor->store_name : 'Unknown',
                        'items' => $items->map(function($item) {
                            return [
                                'product_name' => $item->product_name,
                                'sku' => $item->product_sku,
                                'quantity' => $item->quantity,
                                'mrp' => $item->mrp,
                                'selling_price' => $item->selling_price,
                                'discount' => $item->coupon_discount,
                                'tax_rate' => $item->tax_rate,
                                'tax_amount' => $item->tax_amount,
                                'total' => $item->final_price
                            ];
                        }),
                        'subtotal' => $items->sum(fn($i) => $i->selling_price * $i->quantity),
                        'discount' => $items->sum('vendor_coupon_share'),
                        'tax' => $items->sum('tax_amount'),
                        'shipping' => $items->sum('shipping_charge'),
                        'grand_total' => $items->sum('final_price')
                    ];
                }),
                'totals' => [
                    'subtotal' => $order->subtotal,
                    'coupon_discount' => $order->coupon_discount,
                    'platform_discount' => $order->platform_coupon_discount,
                    'vendor_discount' => $order->vendor_coupon_discount,
                    'tax_total' => $order->tax_total,
                    'shipping_total' => $order->shipping_total,
                    'grand_total' => $order->grand_total
                ],
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
                'order_status' => $order->status
            ]
        ]);
    }

    /**
     * POST /orders - Create new order from cart
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User token required'
            ], 400);
        }

        $request->validate([
            'address_id' => 'nullable|exists:addresses,id',
            'address' => 'required_without:address_id|string',
            'city' => 'required_without:address_id|string',
            'state' => 'required_without:address_id|string',
            'zip' => 'required_without:address_id|string',
            'country' => 'required_without:address_id|string',
            'name' => 'nullable|string',
            'phone' => 'required|string',
            'payment_method' => 'required|string|in:cod,card,upi,netbanking,wallet'
        ]);

        $cart = Cart::with(['items.product', 'items.product.vendor', 'items.variant'])
            ->where('user_id', $user->id)
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Cart is empty'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Validate all items stock
            $this->validateCartStock($cart);

            // Get or create address
            $addressId = $this->getOrCreateAddress($request, $user);

            // Calculate order totals from cart
            $orderData = $this->prepareOrderDataFromCart($cart, $addressId, $request);

            // Create order
            $order = Order::create($orderData);

            // Create order status history
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'pending',
                'note' => 'Order placed successfully'
            ]);

            // Create order items with complete snapshots
            $this->createOrderItemsFromCart($order, $cart);

            // Process coupon if applied
            if ($cart->coupon_id) {
                $this->processOrderCoupon($cart, $order);
            }

            // Update vendor pending balances
            $this->updateVendorPendingBalancesFromOrder($order);

            // Clear cart
            $this->clearUserCart($cart);

            DB::commit();

            $order->load(['items.product', 'items.vendor', 'address']);

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully',
                'data' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'grand_total' => $order->grand_total,
                    'status' => $order->status,
                    'payment_method' => $order->payment_method,
                    'payment_status' => $order->payment_status,
                    'items_count' => $order->items->count()
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order creation failed: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to place order: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * POST /orders/{id}/cancel - Cancel order
     */
    public function cancel(Request $request, $id)
    {
        $order = Order::with('items')
        ->where('user_id', $request->user()->id)
        ->findOrFail($id);

    // ✅ Change $order->status to $order->order_status
    $cancellableStatuses = ['pending', 'processing', 'confirmed'];
    
    if (!in_array($order->order_status, $cancellableStatuses)) {
        return response()->json([
            'success' => false,
            'message' => 'Order cannot be cancelled. Current status: ' . $order->order_status
        ], 400);
    }

        DB::transaction(function () use ($order) {
            // Restore stock
            foreach ($order->items as $item) {
                if ($item->variant_id) {
                    $variant = \App\Models\ProductVariant::find($item->variant_id);
                    if ($variant) {
                        $variant->increment('stock', $item->quantity);
                    }
                } else {
                    Product::where('id', $item->product_id)->increment('stock', $item->quantity);
                }
                
                // Decrement sold count
                Product::where('id', $item->product_id)->decrement('sold_count', $item->quantity);
            }

            // Update order status
            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now()
            ]);

            // Add status history
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'cancelled',
                'note' => 'Order cancelled by user'
            ]);

            // Reverse vendor pending balances
            $this->reverseVendorPendingBalances($order);
        });

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully'
        ]);
    }

    /**
     * POST /orders/{id}/return - Request return
     */
    public function return(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'exists:order_items,id'
        ]);

        $order = Order::where('user_id', $request->user()->id)
            ->findOrFail($id);

        if ($order->status !== 'delivered') {
            return response()->json([
                'success' => false,
                'message' => 'Only delivered orders can be returned'
            ], 400);
        }

        if ($order->return_requested) {
            return response()->json([
                'success' => false,
                'message' => 'Return already requested for this order'
            ], 400);
        }

        $itemsToReturn = $request->item_ids 
            ? $order->items()->whereIn('id', $request->item_ids)->get()
            : $order->items;

        if ($itemsToReturn->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No items selected for return'
            ], 400);
        }

        DB::transaction(function () use ($order, $itemsToReturn, $request) {
            $order->update([
                'return_requested' => true,
                'return_reason' => $request->reason,
                'return_requested_at' => now()
            ]);

            foreach ($itemsToReturn as $item) {
                $item->update([
                    'return_requested' => true,
                    'return_reason' => $request->reason,
                    'return_requested_at' => now()
                ]);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'return_requested',
                'note' => 'Return requested by user. Reason: ' . $request->reason
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Return request submitted successfully',
            'data' => [
                'items_count' => $itemsToReturn->count(),
                'expected_processing_days' => 3
            ]
        ]);
    }

    /**
     * POST /orders/{id}/exchange - Request exchange
     */
    public function exchange(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
            'exchange_product_id' => 'required|exists:products,id',
            'exchange_variant_id' => 'nullable|exists:product_variants,id'
        ]);

        $order = Order::where('user_id', $request->user()->id)
            ->findOrFail($id);

        if ($order->status !== 'delivered') {
            return response()->json([
                'success' => false,
                'message' => 'Only delivered orders can be exchanged'
            ], 400);
        }

        if ($order->exchange_requested) {
            return response()->json([
                'success' => false,
                'message' => 'Exchange already requested for this order'
            ], 400);
        }

        $exchangeProduct = Product::findOrFail($request->exchange_product_id);
        
        DB::transaction(function () use ($order, $request, $exchangeProduct) {
            $order->update([
                'exchange_requested' => true,
                'exchange_reason' => $request->reason,
                'exchange_product_id' => $request->exchange_product_id,
                'exchange_variant_id' => $request->exchange_variant_id,
                'exchange_requested_at' => now()
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'exchange_requested',
                'note' => 'Exchange requested by user. Product: ' . $exchangeProduct->name
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Exchange request submitted successfully',
            'data' => [
                'exchange_product' => $exchangeProduct->name,
                'status' => 'pending_approval'
            ]
        ]);
    }

    /**
     * GET /orders/{id}/status-history - Get status history
     */
    public function statusHistory(Request $request, $id)
    {
        $order = Order::where('user_id', $request->user()->id)
            ->with('statusHistory')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $order->statusHistory->map(function($history) {
                return [
                    'status' => $history->status,
                    'note' => $history->note,
                    'created_at' => $history->created_at->format('Y-m-d H:i:s'),
                    'formatted_date' => $history->created_at->diffForHumans()
                ];
            })
        ]);
    }

    // ==================== PRIVATE HELPER METHODS ====================

    private function calculateOrderSummary($order)
{
    return [
        'items_count' => $order->items->count(),
        'total_quantity' => $order->items->sum('quantity'),
        // ✅ Change $order->status to $order->order_status
        'can_cancel' => in_array($order->order_status, ['pending', 'processing']),
        'can_return' => $order->order_status === 'delivered' && !$order->return_requested,
        'can_exchange' => $order->order_status === 'delivered' && !$order->exchange_requested,
        'payment_due' => $order->payment_status !== 'paid' ? $order->grand_total : 0,
        'refund_amount' => $order->order_status === 'cancelled' ? $order->grand_total : 0
    ];
}

    private function getOrderTimeline($order)
{
    $timeline = [];
    
    if ($order->created_at) {
        $timeline[] = [
            'status' => 'Order Placed',
            'date' => $order->created_at->format('Y-m-d H:i:s'),
            'completed' => true
        ];
    }
    
    // ✅ Change $order->status to $order->order_status
    if ($order->order_status !== 'pending' && $order->updated_at) {
        $timeline[] = [
            'status' => 'Order Confirmed',
            'date' => $order->updated_at->format('Y-m-d H:i:s'),
            'completed' => true
        ];
    }
    
    if ($order->shipped_at) {
        $timeline[] = [
            'status' => 'Shipped',
            'date' => $order->shipped_at->format('Y-m-d H:i:s'),
            'completed' => true
        ];
    }
    
    if ($order->delivered_at) {
        $timeline[] = [
            'status' => 'Delivered',
            'date' => $order->delivered_at->format('Y-m-d H:i:s'),
            'completed' => true
        ];
    }
    
    return $timeline;
}

    private function calculateEstimatedDelivery($order)
    {
        if ($order->delivered_at) {
            return $order->delivered_at->format('Y-m-d');
        }
        
        if ($order->shipped_at) {
            return $order->shipped_at->addDays(5)->format('Y-m-d');
        }
        
        return now()->addDays(7)->format('Y-m-d');
    }

    private function validateCartStock($cart)
    {
        foreach ($cart->items as $item) {
            $stock = $item->variant_id ? 
                ($item->variant->stock ?? 0) : 
                ($item->product->stock ?? 0);
                
            if ($item->quantity > $stock) {
                throw new \Exception("Insufficient stock for {$item->product->name}. Only {$stock} available.");
            }
        }
    }

    private function getOrCreateAddress($request, $user)
    {
        if ($request->address_id) {
            return $request->address_id;
        }

        $address = \App\Models\Address::create([
            'user_id' => $user->id,
            'name' => $request->name ?? $user->name,
            'phone' => $request->phone,
            'address_line1' => $request->address,
            'address_line2' => $request->address_line2 ?? null,
            'city' => $request->city,
            'state' => $request->state,
            'country' => $request->country,
            'postal_code' => $request->zip,
            'is_default' => false
        ]);

        return $address->id;
    }

    private function prepareOrderDataFromCart($cart, $addressId, $request)
    {
        $totals = $this->calculateCartTotals($cart);
        
        return [
            'user_id' => $request->user()->id,
            'order_number' => $this->generateOrderNumber(),
            'address_id' => $addressId,
            'coupon_id' => $cart->coupon_id,
            'status' => 'pending',
            'payment_status' => $request->payment_method === 'cod' ? 'pending' : 'pending',
            'payment_method' => $request->payment_method,
            'subtotal' => $totals['subtotal'],
            'coupon_discount' => $totals['coupon_discount'],
            'platform_coupon_discount' => $totals['platform_coupon_discount'],
            'vendor_coupon_discount' => $totals['vendor_coupon_discount'],
            'tax_total' => $totals['tax_total'],
            'shipping_total' => $totals['shipping_total'],
            'grand_total' => $totals['grand_total'],
            'settlement_status' => 'pending'
        ];
    }

    private function calculateCartTotals($cart)
    {
        $subtotal = $cart->items->sum(fn($i) => $i->selling_price * $i->quantity);
        $shippingTotal = $cart->items->sum(fn($i) => ($i->estimated_shipping ?? 0) * $i->quantity);
        $taxTotal = $cart->items->sum(fn($i) => ($i->selling_price * $i->quantity * $i->tax_rate / 100));
        
        return [
            'subtotal' => $subtotal,
            'shipping_total' => $shippingTotal,
            'tax_total' => $taxTotal,
            'coupon_discount' => $cart->coupon_discount ?? 0,
            'platform_coupon_discount' => $cart->platform_coupon_discount ?? 0,
            'vendor_coupon_discount' => $cart->vendor_coupon_discount ?? 0,
            'grand_total' => $subtotal + $shippingTotal + $taxTotal - ($cart->coupon_discount ?? 0)
        ];
    }

    private function createOrderItemsFromCart($order, $cart)
{
    $orderSubtotal = $cart->items->sum(fn($i) => $i->selling_price * $i->quantity);
    
    foreach ($cart->items as $item) {
        $itemSubtotal = $item->selling_price * $item->quantity;
        $itemRatio = $orderSubtotal > 0 ? $itemSubtotal / $orderSubtotal : 0;
        
        $couponDiscount = ($cart->coupon_discount ?? 0) * $itemRatio;
        $vendorCouponShare = ($cart->vendor_coupon_discount ?? 0) * $itemRatio;
        $adminCouponShare = ($cart->platform_coupon_discount ?? 0) * $itemRatio;
        
        $taxAmount = ($item->selling_price * $item->quantity * $item->tax_rate / 100);
        
        // Calculate commission
        $vendor = $item->product->vendor;
        $commissionAmount = 0;
        if ($vendor) {
            if ($vendor->commission_type === 'percentage') {
                $commissionAmount = ($vendor->commission_rate / 100) * $itemSubtotal;
            } else {
                $commissionAmount = min($vendor->commission_rate, $itemSubtotal);
            }
        }
        
        $vendorPayout = $itemSubtotal - $vendorCouponShare - $commissionAmount;
        
        // Add shipping if vendor manages
        if ($item->shipping_mode === 'vendor') {
            $vendorPayout += ($item->estimated_shipping ?? 0) * $item->quantity;
        }
        
        // ✅ FIX: Get vendor_id and creator_id from product
        $product = $item->product;
        $vendorId = $product->vendor_id; // Can be null
        $creatorId = $product->user_id; // The user who created the product
        
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $item->product_id,
            'variant_id' => $item->variant_id,
            'vendor_id' => $vendorId, // ✅ Can be null
            'creator_id' => $creatorId, // ✅ Add creator_id
            'product_name' => $item->product->name,
            'product_sku' => $item->variant_id ? ($item->variant->sku ?? $item->product->sku) : $item->product->sku,
            'quantity' => $item->quantity,
            'mrp' => $item->mrp,
            'selling_price' => $item->selling_price,
            'coupon_discount' => $couponDiscount,
            'vendor_coupon_share' => $vendorCouponShare,
            'admin_coupon_share' => $adminCouponShare,
            'tax_rate' => $item->tax_rate,
            'tax_amount' => $taxAmount,
            'shipping_mode' => $item->shipping_mode,
            'shipping_charge' => ($item->estimated_shipping ?? 0) * $item->quantity,
            'commission_type' => $vendor->commission_type ?? 'percentage',
            'commission_rate' => $vendor->commission_rate ?? 0,
            'commission_amount' => $commissionAmount,
            'final_price' => $itemSubtotal - $couponDiscount + $taxAmount + (($item->estimated_shipping ?? 0) * $item->quantity),
            'vendor_payout' => $vendorPayout,
            'settlement_status' => 'pending',
            'status' => 'pending'
        ]);
        
        // Update stock
        if ($item->variant_id) {
            $item->variant->decrement('stock', $item->quantity);
        } else {
            $item->product->decrement('stock', $item->quantity);
        }
        //$item->product->increment('sold_count', $item->quantity);
    }
}

    private function processOrderCoupon($cart, $order)
    {
        $coupon = Coupon::find($cart->coupon_id);
        if ($coupon) {
            CouponUsage::create([
                'coupon_id' => $coupon->id,
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'discount_amount' => $cart->coupon_discount,
                'breakdown' => json_encode([
                    'platform_share' => $cart->platform_coupon_discount,
                    'vendor_share' => $cart->vendor_coupon_discount
                ])
            ]);
            
            $coupon->increment('used_count');
        }
    }

    private function updateVendorPendingBalancesFromOrder($order)
    {
        $itemsByVendor = $order->items->groupBy('vendor_id');
        
        foreach ($itemsByVendor as $vendorId => $items) {
            $totalPayout = $items->sum('vendor_payout');
            
            $wallet = \App\Models\VendorWallet::firstOrCreate(
                ['vendor_id' => $vendorId],
                [
                    'balance' => 0,
                    'pending_balance' => 0,
                    'available_balance' => 0,
                    'total_earned' => 0,
                    'total_withdrawn' => 0,
                    'total_commission_paid' => 0
                ]
            );
            
            $wallet->increment('pending_balance', $totalPayout);
            $wallet->increment('total_earned', $totalPayout);
            
            foreach ($items as $item) {
                \App\Models\VendorTransaction::create([
                    'vendor_wallet_id' => $wallet->id,
                    'vendor_id' => $vendorId,
                    'order_item_id' => $item->id,
                    'amount' => $item->selling_price * $item->quantity,
                    'coupon_amount' => $item->vendor_coupon_share,
                    'tax_amount' => $item->tax_amount,
                    'shipping_amount' => $item->shipping_charge,
                    'commission' => $item->commission_amount,
                    'commission_rate' => $item->commission_rate,
                    'net_amount' => $item->vendor_payout,
                    'type' => 'credit',
                    'description' => "Earnings from Order #{$order->order_number}",
                    'status' => 'pending',
                    'reference_id' => $order->id
                ]);
            }
        }
    }

    private function reverseVendorPendingBalances($order)
    {
        $itemsByVendor = $order->items->groupBy('vendor_id');
        
        foreach ($itemsByVendor as $vendorId => $items) {
            $totalPayout = $items->sum('vendor_payout');
            
            $wallet = \App\Models\VendorWallet::where('vendor_id', $vendorId)->first();
            if ($wallet) {
                $wallet->decrement('pending_balance', $totalPayout);
                $wallet->decrement('total_earned', $totalPayout);
            }
        }
    }

    private function clearUserCart($cart)
    {
        $cart->items()->delete();
        $cart->update([
            'coupon_id' => null,
            'coupon_discount' => 0,
            'platform_coupon_discount' => 0,
            'vendor_coupon_discount' => 0,
            'coupon_data' => null,
            'subtotal' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 0
        ]);
    }

    private function generateOrderNumber()
    {
        $prefix = 'ORD';
        $date = now()->format('Ymd');
        $random = strtoupper(substr(uniqid(), -6));
        
        $orderNumber = $prefix . $date . $random;
        
        while (Order::where('order_number', $orderNumber)->exists()) {
            $random = strtoupper(substr(uniqid(), -6));
            $orderNumber = $prefix . $date . $random;
        }
        
        return $orderNumber;
    }

/**
 * GET /orders/{id}/shipments - User view with split shipments
 */
public function shipmentDetails(Request $request, $id)
{
    $order = Order::where('id', $id)
        ->where('user_id', $request->user()->id)
        ->firstOrFail();
    
    $shipments = Shipment::where('order_id', $id)
        ->with(['vendor', 'items.product'])
        ->get()
        ->map(function($shipment) {
            return [
                'id' => $shipment->id,
                'type' => $shipment->shipping_mode === 'vendor' ? 'Vendor Shipping' : 'Marketplace Shipping',
                'vendor_name' => $shipment->vendor ? $shipment->vendor->store_name : 'Marketplace',
                'carrier' => $shipment->carrier,
                'tracking_number' => $shipment->tracking_number,
                'status' => $shipment->status,
                'shipped_at' => $shipment->shipped_at,
                'estimated_delivery' => $shipment->estimated_delivery,
                'delivered_at' => $shipment->delivered_at,
                'items' => $shipment->items->map(function($item) {
                    return [
                        'product_name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'price' => $item->selling_price
                    ];
                })
            ];
        });
    
    // ✅ Split by shipping mode (existing column)
    $vendorShipments = $shipments->filter(fn($s) => $s['type'] === 'Vendor Shipping');
    $marketplaceShipments = $shipments->filter(fn($s) => $s['type'] === 'Marketplace Shipping');
    
    return response()->json([
        'success' => true,
        'data' => [
            'order_number' => $order->order_number,
            'all_shipments' => $shipments,
            'vendor_shipments' => $vendorShipments->values(),
            'marketplace_shipments' => $marketplaceShipments->values(),
        ]
    ]);
}
}