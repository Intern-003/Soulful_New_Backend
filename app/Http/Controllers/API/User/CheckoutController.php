<?php
// app/Http/Controllers/API/User/CheckoutController.php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Vendor;
use App\Models\VendorWallet;
use App\Models\VendorTransaction;
use App\Services\OrderCalculationService;
use App\Services\TaxService;
use App\Services\CommissionService;
use Illuminate\Support\Facades\Log;
use App\Helpers\VendorHelper;

class CheckoutController extends Controller
{
    protected $orderCalculationService;
    protected $taxService;
    protected $commissionService;

    public function __construct(
        OrderCalculationService $orderCalculationService,
        TaxService $taxService,
        CommissionService $commissionService
    ) {
        $this->orderCalculationService = $orderCalculationService;
        $this->taxService = $taxService;
        $this->commissionService = $commissionService;
    }

    /**
     * Get checkout summary with vendor breakdown
     */
    public function summary(Request $request)
    {
        $user = $request->user();
        $guestToken = $request->header('Guest-Token');

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User must be logged in for checkout'
            ], 400);
        }

        $cart = Cart::with(['items.product', 'items.product.vendor', 'items.variant'])
            ->firstOrCreate(['user_id' => $user->id]);

        if ($guestToken && $guestToken !== 'null') {
            app(CartController::class)->mergeGuestCart($guestToken, $cart);
        }

        // Calculate detailed checkout summary
        $checkoutData = $this->calculateCheckoutSummary($cart);

        return response()->json([
            'success' => true,
            'data' => $checkoutData
        ]);
    }

    /**
     * Calculate complete checkout summary with vendor splits
     */
    // Replace the calculateCheckoutSummary method with this:

    protected function calculateCheckoutSummary($cart)
    {
        $itemsByVendor = $cart->items->groupBy('vendor_id');
        $vendorSummaries = [];

        // Calculate totals directly
        $subtotal = $cart->items->sum(fn($i) => $i->selling_price * $i->quantity);
        $shippingTotal = $cart->items->sum(fn($i) => ($i->estimated_shipping ?? 0) * $i->quantity);
        $taxTotal = 0;

        foreach ($cart->items as $item) {
            $taxTotal += ($item->selling_price * $item->quantity * ($item->tax_rate ?? 18)) / 100;
        }

        $cartTotals = [
            'subtotal' => round($subtotal, 2),
            'shipping_total' => round($shippingTotal, 2),
            'tax_total' => round($taxTotal, 2),
            'coupon_discount' => round($cart->coupon_discount ?? 0, 2),
            'platform_coupon_discount' => round($cart->platform_coupon_discount ?? 0, 2),
            'vendor_coupon_discount' => round($cart->vendor_coupon_discount ?? 0, 2),
            'grand_total' => round($subtotal + $shippingTotal + $taxTotal - ($cart->coupon_discount ?? 0), 2)
        ];

        foreach ($itemsByVendor as $vendorId => $items) {
            // Remove the 'with('settings')' - just find the vendor
            $vendor = Vendor::find($vendorId);
            $vendorSubtotal = $items->sum(fn($i) => $i->selling_price * $i->quantity);

            // Calculate vendor shipping - simple calculation
            $vendorShipping = $items->sum(fn($i) => ($i->estimated_shipping ?? 0) * $i->quantity);

            // Calculate vendor tax
            $vendorTax = 0;
            foreach ($items as $item) {
                $vendorTax += ($item->selling_price * $item->quantity * ($item->tax_rate ?? 18)) / 100;
            }

            // Calculate vendor coupon share
            $vendorCouponShare = 0;
            if ($cart->vendor_coupon_discount && $vendorSubtotal > 0) {
                $vendorCouponShare = ($vendorSubtotal / $subtotal) * $cart->vendor_coupon_discount;
            }

            $vendorSummaries[] = [
                'vendor_id' => $vendorId,
                'vendor_name' => $vendor ? $vendor->store_name : 'Unknown',
                'items' => $items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->name,
                        'variant_name' => $item->variant ? $item->variant->sku : null,
                        'quantity' => $item->quantity,
                        'mrp' => $item->mrp,
                        'selling_price' => $item->selling_price,
                        'total' => $item->selling_price * $item->quantity,
                        'tax_rate' => $item->tax_rate,
                        'image' => $item->product->images->first()->image_url ?? null
                    ];
                }),
                'subtotal' => round($vendorSubtotal, 2),
                'shipping' => round($vendorShipping, 2),
                'tax' => round($vendorTax, 2),
                'coupon_discount' => round($vendorCouponShare, 2),
                'grand_total' => round($vendorSubtotal + $vendorShipping + $vendorTax - $vendorCouponShare, 2)
            ];
        }

        return [
            'cart_id' => $cart->id,
            'items_count' => $cart->items->count(),
            'vendor_summaries' => $vendorSummaries,
            'totals' => $cartTotals,
            'applied_coupon' => $cart->coupon_id ? [
                'id' => $cart->coupon_id,
                'discount' => $cart->coupon_discount,
                'platform_share' => $cart->platform_coupon_discount,
                'vendor_share' => $cart->vendor_coupon_discount
            ] : null
        ];
    }

    /**
     * Validate cart before checkout
     */
    public function validateCheckout(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User token required'
            ], 400);
        }

        $cart = Cart::with('items.product')->where('user_id', $user->id)->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Cart is empty'
            ], 400);
        }

        $errors = [];
        $warnings = [];

        foreach ($cart->items as $item) {
            $product = $item->product;

            if (!$product) {
                $errors[] = "Product ID {$item->product_id} no longer exists.";
                continue;
            }

            // Check stock
            $stock = $item->variant_id ?
                ($item->variant->stock ?? 0) :
                ($product->stock ?? 0);

            if ($item->quantity > $stock) {
                $errors[] = "{$product->name} only has {$stock} items available.";
            }

            // Check product status
            if ($product->approval_status !== 'approved' || !$product->status) {
                $errors[] = "{$product->name} is no longer available for purchase.";
            }

            // Price change warning
            $currentPrice = $this->getCurrentPrice($product, $item->variant);
            if ($item->selling_price != $currentPrice) {
                $warnings[] = "{$product->name} price changed from {$item->selling_price} to {$currentPrice}.";
                $item->selling_price = $currentPrice;
                $item->save();
            }
        }

        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'message' => 'Checkout validation failed',
                'errors' => $errors
            ], 422);
        }

        // Calculate totals directly
        $subtotal = $cart->items->sum(fn($i) => $i->selling_price * $i->quantity);
        $shippingTotal = $cart->items->sum(fn($i) => ($i->estimated_shipping ?? 0) * $i->quantity);
        $taxTotal = 0;
        foreach ($cart->items as $item) {
            $taxTotal += ($item->selling_price * $item->quantity * ($item->tax_rate ?? 18)) / 100;
        }

        $cart->update([
            'subtotal' => $subtotal,
            'shipping_total' => $shippingTotal,
            'tax_total' => $taxTotal,
            'grand_total' => $subtotal + $shippingTotal + $taxTotal - ($cart->coupon_discount ?? 0)
        ]);

        $updatedTotals = [
            'subtotal' => round($subtotal, 2),
            'shipping_total' => round($shippingTotal, 2),
            'tax_total' => round($taxTotal, 2),
            'coupon_discount' => round($cart->coupon_discount ?? 0, 2),
            'grand_total' => round($subtotal + $shippingTotal + $taxTotal - ($cart->coupon_discount ?? 0), 2)
        ];

        return response()->json([
            'success' => true,
            'message' => 'Cart is valid for checkout',
            'warnings' => $warnings,
            'updated_totals' => $updatedTotals
        ]);
    }

    /**
     * Process complete checkout with order creation
     */
    /**
     * Process complete checkout with order creation
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'address_id' => 'nullable|exists:addresses,id',
            'payment_method' => 'required|in:cod,card,upi,netbanking,wallet',
            'notes' => 'nullable|string|max:500',
            // New address fields (used if address_id is not provided)
            'name' => 'required_without:address_id|string',
            'phone' => 'required_without:address_id|string',
            'address_line1' => 'required_without:address_id|string',
            'address_line2' => 'nullable|string',
            'city' => 'required_without:address_id|string',
            'state' => 'required_without:address_id|string',
            'pincode' => 'required_without:address_id|string',
            'country' => 'nullable|string',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User token required'
            ], 400);
        }

        // Get or create address
        $addressId = $request->address_id;

        if (!$addressId) {
            // Create new address
            $address = \App\Models\Address::create([
                'user_id' => $user->id,
                'name' => $request->name,
                'phone' => $request->phone,
                'address_line1' => $request->address_line1,
                'address_line2' => $request->address_line2,
                'city' => $request->city,
                'state' => $request->state,
                'country' => $request->country ?? 'India',
                'postal_code' => $request->pincode,
                'is_default' => false
            ]);
            $addressId = $address->id;
        } else {
            $address = $user->addresses()->find($addressId);
            if (!$address) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid shipping address selected'
                ], 400);
            }
        }

        // Rest of your checkout logic continues...
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

            // Validate all items before processing
            $this->validateItemsStock($cart);

            // Calculate final order data
            $orderData = $this->prepareOrderData($cart, $address, null, $request);

            // Create order
            $order = $this->createOrder($orderData, $user, $cart, $request);

            // Create order items with complete financial snapshots
            $this->createOrderItems($order, $cart);

            // Process coupon usage
            if ($cart->coupon_id) {
                $this->processCouponUsage($cart, $order);
            }

            // Update vendor pending balances
            $this->updateVendorPendingBalances($order);

            // Clear cart
            $this->clearCartAfterCheckout($cart);

            DB::commit();

            // Load order relations for response
            $order->load(['items.product', 'items.vendor', 'address']);

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully',
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'grand_total' => $order->grand_total,
                    'payment_method' => $order->payment_method,
                    'payment_status' => $order->payment_status,
                    'status' => $order->order_status,
                    'items_count' => $order->items->count(),
                    'estimated_delivery' => now()->addDays(5)->format('Y-m-d'),
                    'next_step' => $order->payment_method === 'cod' ? 'order_confirmed' : 'proceed_to_payment'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Checkout failed: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process order: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Validate stock for all cart items
     */
    protected function validateItemsStock($cart)
    {
        foreach ($cart->items as $item) {
            $product = $item->product;
            $stock = $item->variant_id ?
                ($item->variant->stock ?? 0) :
                ($product->stock ?? 0);

            if ($item->quantity > $stock) {
                throw new \Exception("Insufficient stock for {$product->name}. Only {$stock} available.");
            }
        }
    }

    /**
     * Prepare order data array
     */
    protected function prepareOrderData($cart, $address, $billingAddress, $request)
    {
        // Calculate totals directly
        $subtotal = $cart->items->sum(fn($i) => $i->selling_price * $i->quantity);
        $shippingTotal = $cart->items->sum(fn($i) => ($i->estimated_shipping ?? 0) * $i->quantity);
        $taxTotal = 0;
        foreach ($cart->items as $item) {
            $taxTotal += ($item->selling_price * $item->quantity * ($item->tax_rate ?? 18)) / 100;
        }

        return [
            'address_id' => $address->id,  // ✅ ADD THIS LINE
            'subtotal' => $subtotal,
            'coupon_discount' => $cart->coupon_discount ?? 0,
            'platform_coupon_discount' => $cart->platform_coupon_discount ?? 0,
            'vendor_coupon_discount' => $cart->vendor_coupon_discount ?? 0,
            'tax_total' => $taxTotal,
            'shipping_total' => $shippingTotal,
            'grand_total' => $subtotal + $shippingTotal + $taxTotal - ($cart->coupon_discount ?? 0),
            //'coupon_id' => $cart->coupon_id,
            // 'shipping_address' => json_encode([
            //     'name' => $address->name,
            //     'phone' => $address->phone,
            //     'address_line1' => $address->address_line1,
            //     'address_line2' => $address->address_line2,
            //     'city' => $address->city,
            //     'state' => $address->state,
            //     'pincode' => $address->pincode,
            //     'country' => $address->country
            // ]),
            // 'billing_address' => $billingAddress ? json_encode([
            //     'name' => $billingAddress->name,
            //     'phone' => $billingAddress->phone,
            //     'address_line1' => $billingAddress->address_line1,
            //     'address_line2' => $billingAddress->address_line2,
            //     'city' => $billingAddress->city,
            //     'state' => $billingAddress->state,
            //     'pincode' => $billingAddress->pincode,
            //     'country' => $billingAddress->country
            // ]) : null,
            //'notes' => $request->notes,
            'payment_method' => $request->payment_method
        ];
    }
    /**
     * Create order record
     */

    protected function createOrder($orderData, $user, $cart, $request)
    {
        return Order::create([
            // Required fields
            'order_number' => $this->generateOrderNumber(),
            'user_id' => $user->id,
            'address_id' => $orderData['address_id'],

            // Status fields
            'order_status' => 'pending',
            'payment_status' => $request->payment_method === 'cod' ? 'pending' : 'pending',
            'settlement_status' => 'pending',
            'payment_method' => $request->payment_method,

            // Financial fields (note: database column names)
            'subtotal' => $orderData['subtotal'],
            'discount' => $orderData['coupon_discount'],      // database uses 'discount'
            'tax' => $orderData['tax_total'],                  // database uses 'tax'
            'shipping_total' => $orderData['shipping_total'],
            'grand_total' => $orderData['grand_total'],

            // Coupon split fields
            'platform_coupon_discount' => $orderData['platform_coupon_discount'],
            'vendor_coupon_discount' => $orderData['vendor_coupon_discount'],

            // Additional fields
            //'notes' => $orderData['notes']
        ]);
    }
    /**
     * Create order items with complete financial snapshots
     */
    // In CheckoutController.php createOrderItems method

protected function createOrderItems($order, $cart)
{
    foreach ($cart->items as $item) {
        $product = $item->product;
        $vendor = $product->vendor;

        $itemSubtotal = $item->selling_price * $item->quantity;

        // Calculate coupon distribution
        $couponDiscount = 0;
        $vendorCouponShare = 0;
        $adminCouponShare = 0;

        if ($cart->coupon_discount > 0 && $cart->subtotal > 0) {
            $itemRatio = $itemSubtotal / $cart->subtotal;
            $couponDiscount = $cart->coupon_discount * $itemRatio;
            $vendorCouponShare = ($cart->vendor_coupon_discount ?? 0) * $itemRatio;
            $adminCouponShare = ($cart->platform_coupon_discount ?? 0) * $itemRatio;
        }

        // Calculate tax
        $taxCalculation = $this->taxService->calculateProductTax(
            ($item->selling_price - ($vendorCouponShare / $item->quantity)) * $item->quantity,
            $item->tax_rate
        );

        // Get product creator info
        $vendorId = $product->vendor_id;
        $creatorId = $product->user_id;

        // ✅ FIX: If vendor_id is null but creator_id exists, create a virtual vendor ID
        $actualVendorId = $vendorId;
        if (is_null($vendorId) && !is_null($creatorId)) {
            $actualVendorId = VendorHelper::getVendorIdForCreator($creatorId);
        }

        // Calculate commission
        $commissionAmount = 0;
        $commissionRate = 0;
        $commissionType = 'percentage';

        if ($vendor) {
            // Vendor exists - use vendor commission settings
            $commissionRateInfo = $this->commissionService->getProductCommissionRate($product, $vendor);
            $commissionAmount = $this->commissionService->calculateProductCommission(
                $product,
                $item->quantity,
                $vendor
            );
            $commissionRate = $commissionRateInfo['rate'] ?? 0;
            $commissionType = $commissionRateInfo['type'] ?? 'percentage';
        } else {
            // No vendor - individual seller
            // Set default platform commission (e.g., 10%)
            $commissionRate = 10;
            $commissionType = 'percentage';
            $commissionAmount = ($itemSubtotal * $commissionRate) / 100;
        }

        // Calculate vendor payout
        $vendorPayout = $itemSubtotal - $vendorCouponShare - $commissionAmount;

        // Add shipping if vendor manages shipping
        if ($item->shipping_mode === 'vendor') {
            $itemShipping = ($item->estimated_shipping ?? 0) * $item->quantity;
            $vendorPayout += $itemShipping;
        }

        // Get SKU with fallback
        $productSku = $product->sku;
        
        if ($item->variant_id && $item->variant && $item->variant->sku) {
            $productSku = $item->variant->sku;
        }
        
        if (!$productSku) {
            $productSku = 'PROD-' . str_pad($product->id, 6, '0', STR_PAD_LEFT);
        }

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'variant_id' => $item->variant_id,
            'vendor_id' => $actualVendorId, // ✅ Can be integer or string
            'creator_id' => $creatorId,
            'product_name' => $product->name,
            'product_sku' => $productSku,
            'quantity' => $item->quantity,
            'mrp' => $item->mrp,
            'selling_price' => $item->selling_price,
            'coupon_discount' => $couponDiscount,
            'coupon_funded_by' => $cart->coupon_id ?
                Coupon::find($cart->coupon_id)?->funded_by : 'vendor',
            'vendor_coupon_share' => $vendorCouponShare,
            'admin_coupon_share' => $adminCouponShare,
            'tax_rate' => $item->tax_rate,
            'tax_amount' => $taxCalculation['total_tax'],
            'tax_breakdown' => json_encode($taxCalculation['breakdown']),
            'shipping_mode' => $item->shipping_mode,
            'shipping_charge' => ($item->estimated_shipping ?? 0) * $item->quantity,
            'commission_type' => $commissionType,
            'commission_rate' => $commissionRate,
            'commission_amount' => $commissionAmount,
            'final_price' => $itemSubtotal - $couponDiscount + $taxCalculation['total_tax'] + (($item->estimated_shipping ?? 0) * $item->quantity),
            'vendor_payout' => $vendorPayout,
            'settlement_status' => 'pending'
        ]);

        // Update product stock
        $this->updateProductStock($product, $item);
    }
}

    /**
     * Update product/variant stock after order
     */
    /**
     * Update product/variant stock after order
     */
    protected function updateProductStock($product, $cartItem)
    {
        if ($cartItem->variant_id) {
            $variant = $product->variants()->find($cartItem->variant_id);
            if ($variant) {
                $variant->decrement('stock', $cartItem->quantity);
            }
        } else {
            $product->decrement('stock', $cartItem->quantity);
        }
        // ❌ REMOVE this line - no sold_count column
        // $product->increment('sold_count', $cartItem->quantity);
    }

    /**
     * Process coupon usage record
     */
    protected function processCouponUsage($cart, $order)
    {
        CouponUsage::create([
            'coupon_id' => $cart->coupon_id,
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'discount_amount' => $cart->coupon_discount,
            'breakdown' => json_encode([
                'platform_share' => $cart->platform_coupon_discount,
                'vendor_share' => $cart->vendor_coupon_discount,
                'coupon_data' => $cart->coupon_data
            ])
        ]);

        Coupon::where('id', $cart->coupon_id)->increment('used_count');
    }

    /**
     * Update vendor pending balances for settlement
     */
    protected function updateVendorPendingBalances($order)
{
    $orderItemsByVendor = $order->items->groupBy('vendor_id');

    foreach ($orderItemsByVendor as $vendorId => $items) {
        // ✅ Skip if vendor_id is null
        if (is_null($vendorId)) {
            continue;
        }

        $totalPayout = $items->sum('vendor_payout');
        
        // ✅ Check if it's a creator virtual vendor ID
        $isCreator = VendorHelper::isCreatorVendor($vendorId);
        $creatorId = $isCreator ? VendorHelper::getCreatorIdFromVendorId($vendorId) : null;

        // ✅ For creators, check if we want to track them (you can enable/disable this)
        $trackCreators = true; // Set to false to skip creators
        
        if ($isCreator && !$trackCreators) {
            continue;
        }

        // ✅ FirstOrCreate will work with both integer and string vendor IDs
        $wallet = VendorWallet::firstOrCreate(
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

        // Create transaction record for each item
        foreach ($items as $item) {
            VendorTransaction::create([
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
                'description' => $isCreator 
                    ? "Earnings from Order #{$order->order_number} - Creator #{$creatorId} - Item #{$item->id}"
                    : "Earnings from Order #{$order->order_number} - Item #{$item->id}",
                'status' => 'pending',
                'reference_id' => $order->id
            ]);
        }
    }
}

    /**
     * Clear cart after successful checkout
     */
    protected function clearCartAfterCheckout($cart)
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

    /**
     * Generate unique order number
     */
    protected function generateOrderNumber()
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
     * Get current selling price
     */
    protected function getCurrentPrice($product, $variant = null)
    {
        if ($variant && $variant->discount_price) {
            return $variant->discount_price;
        }
        if ($variant && $variant->price) {
            return $variant->price;
        }
        if ($product->discount_price) {
            return $product->discount_price;
        }
        return $product->price;
    }
}