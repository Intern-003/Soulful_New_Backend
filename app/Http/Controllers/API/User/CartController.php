<?php
// app/Http/Controllers/API/User/CartController.php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Vendor;
use App\Services\TaxService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    protected $taxService;

    public function __construct(TaxService $taxService)
    {
        $this->taxService = $taxService;
    }

    /* =========================
        RESPONSE FORMAT
    ========================= */
    private function formatCartResponse($cart, $guestToken = null)
    {
        $cart->loadMissing([
            'items.product.images',
            'items.product.vendor',
            'items.variant'
        ]);

        $totals = $this->calculateTotals($cart);
        
        // Group items by vendor for better display
        $itemsByVendor = $cart->items->groupBy(function($item) {
            return $item->vendor_id ?? $item->product->vendor_id;
        });

        $vendorTotals = [];
        foreach ($itemsByVendor as $vendorId => $items) {
            $vendor = Vendor::find($vendorId);
            $vendorSubtotal = $items->sum(fn($i) => $i->selling_price * $i->quantity);
            $vendorTotals[] = [
                'vendor_id' => $vendorId,
                'vendor_name' => $vendor ? $vendor->store_name : 'Unknown',
                'subtotal' => $vendorSubtotal,
                'items' => $items,
                'shipping_estimate' => $this->estimateVendorShipping($items, $vendor)
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'cart' => $cart,
                'items_by_vendor' => $vendorTotals,
                'totals' => $totals
            ],
            'guest_token' => $guestToken
        ]);
    }

    /* =========================
        GET CART
    ========================= */
    public function getCart(Request $request)
    {
        $user = Auth::guard('sanctum')->user();
        $guestToken = $request->header('Guest-Token');

        if (!$user && !$guestToken) {
            $guestToken = bin2hex(random_bytes(16));
            $cart = Cart::create(['guest_token' => $guestToken]);
            return $this->formatCartResponse($cart, $guestToken);
        }

        $cart = Cart::with(['items.product.images', 'items.product.vendor', 'items.variant'])
            ->when($user, fn($q) => $q->where('user_id', $user->id))
            ->when(!$user && $guestToken, fn($q) => $q->where('guest_token', $guestToken))
            ->first();

        if (!$cart) {
            return response()->json([
                'success' => true,
                'data' => [
                    'cart' => ['items' => []],
                    'items_by_vendor' => [],
                    'totals' => $this->emptyTotals()
                ],
                'guest_token' => $guestToken
            ]);
        }

        return $this->formatCartResponse($cart, $user ? null : $guestToken);
    }

    /* =========================
        ADD TO CART - ENHANCED WITH PRICE SNAPSHOT
    ========================= */
    /* =========================
    ADD TO CART - ENHANCED WITH PRICE SNAPSHOT
========================= */
public function addToCart(Request $request)
{
    $request->validate([
        'product_id' => 'required|exists:products,id',
        'variant_id' => 'nullable|exists:product_variants,id',
        'quantity' => 'required|integer|min:1'
    ]);

    $user = Auth::guard('sanctum')->user();
    $guestToken = $request->header('Guest-Token');

    $product = Product::findOrFail($request->product_id);
    
    // Check product is approved and active
    if ($product->approval_status !== 'approved' || !$product->status) {
        return response()->json([
            'success' => false,
            'message' => 'This product is not available for purchase'
        ], 400);
    }

    $variant = null;
    if ($request->variant_id) {
        $variant = ProductVariant::findOrFail($request->variant_id);
    }

    // Get stock and final price
    $stock = $this->getStock($product, $variant);
    $sellingPrice = $this->getSellingPrice($product, $variant);
    $mrp = $variant ? ($variant->price ?? $product->price) : $product->price;
    $taxRate = $variant && $variant->tax_rate ? $variant->tax_rate : ($product->tax_rate ?? 18);
    $shippingMode = $product->shipping_mode ?? 'vendor';
    $shippingCharge = $variant && $variant->shipping_charge ? $variant->shipping_charge : $product->shipping_charge;

    if ($stock <= 0) {
        return response()->json([
            'success' => false,
            'message' => 'Out of stock'
        ], 422);
    }

    // Get or create cart
    if ($user) {
        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        if ($guestToken) {
            $this->mergeGuestCart($guestToken, $cart);
            $cart->update(['guest_token' => null]);
        }
    } else {
        $guestToken = $guestToken ?? bin2hex(random_bytes(16));
        $cart = Cart::firstOrCreate(['guest_token' => $guestToken]);
    }

    // ✅ FIX: Set vendor_id, fallback to null if product has no vendor
    $vendorId = $product->vendor_id ?? null;

    // Check existing item
    $item = CartItem::where([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'variant_id' => $request->variant_id
    ])->first();

    $currentQty = $item?->quantity ?? 0;
    $newQty = $currentQty + $request->quantity;

    if ($newQty > $stock) {
        return response()->json([
            'success' => false,
            'message' => "Only {$stock} items available"
        ], 422);
    }

    // Calculate estimated tax
    $estimatedTax = $this->taxService->calculateProductTax($sellingPrice * $newQty, $taxRate);

    if ($item) {
        $item->update([
            'quantity' => $newQty,
            'selling_price' => $sellingPrice,
            'price' => $sellingPrice,
            'mrp' => $mrp,
            'tax_rate' => $taxRate,
            'estimated_shipping' => $shippingCharge,
            'shipping_mode' => $shippingMode,
            'vendor_id' => $vendorId // ✅ Update vendor_id if changed
        ]);
    } else {
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => $request->variant_id,
            'vendor_id' => $vendorId, // ✅ Use the nullable vendor_id
            'quantity' => $request->quantity,
            'mrp' => $mrp,
            'selling_price' => $sellingPrice,
            'price' => $sellingPrice,
            'tax_rate' => $taxRate,
            'estimated_shipping' => $shippingCharge ?? 0,
            'shipping_mode' => $shippingMode
        ]);
    }

    // Update cart totals
    $this->updateCartTotals($cart);

    return $this->formatCartResponse($cart, $guestToken);
}

    /* =========================
        UPDATE CART ITEM
    ========================= */
    public function updateCartItem(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        $user = Auth::guard('sanctum')->user();
        $guestToken = $request->header('Guest-Token');

        $item = CartItem::where('id', $id)
            ->whereHas('cart', fn($q) =>
                $user ? $q->where('user_id', $user->id)
                      : $q->where('guest_token', $guestToken)
            )
            ->firstOrFail();

        $stock = $this->getStock($item->product, $item->variant);

        if ($request->quantity > $stock) {
            return response()->json([
                'success' => false,
                'message' => "Only {$stock} items available"
            ], 422);
        }

        $item->update(['quantity' => $request->quantity]);
        
        // Update cart totals
        $this->updateCartTotals($item->cart);

        return $this->formatCartResponse($item->cart, $guestToken);
    }

    /* =========================
        DELETE ITEM
    ========================= */
    public function deleteCartItem(Request $request, $id)
    {
        $user = Auth::guard('sanctum')->user();
        $guestToken = $request->header('Guest-Token');

        $item = CartItem::where('id', $id)
            ->whereHas('cart', fn($q) =>
                $user ? $q->where('user_id', $user->id)
                      : $q->where('guest_token', $guestToken)
            )
            ->firstOrFail();

        $cart = $item->cart;
        $item->delete();
        
        // Update cart totals
        $this->updateCartTotals($cart);

        return $this->formatCartResponse($cart, $guestToken);
    }

    /* =========================
        CLEAR CART
    ========================= */
    public function clearCart(Request $request)
    {
        $user = Auth::guard('sanctum')->user();
        $guestToken = $request->header('Guest-Token');

        $cart = Cart::when($user, fn($q) => $q->where('user_id', $user->id))
            ->when(!$user && $guestToken, fn($q) => $q->where('guest_token', $guestToken))
            ->first();

        if ($cart) {
            $cart->items()->delete();
            $cart->update([
                'coupon_id' => null,
                'coupon_discount' => 0,
                'platform_coupon_discount' => 0,
                'vendor_coupon_discount' => 0
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'cart' => ['items' => []],
                'items_by_vendor' => [],
                'totals' => $this->emptyTotals()
            ],
            'guest_token' => $guestToken
        ]);
    }

    /* =========================
        APPLY COUPON
    ========================= */
    public function applyCoupon(Request $request)
    {
        $request->validate([
            'coupon_code' => 'required|string'
        ]);

        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Please login to apply coupon'
            ], 401);
        }

        $cart = Cart::where('user_id', $user->id)->first();
        
        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Cart is empty'
            ], 400);
        }

        $couponController = app(CouponController::class);
        $result = $couponController->validateAndApplyCoupon($request->coupon_code, $cart);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 422);
        }

        // Update cart with coupon
        $cart->update([
            'coupon_id' => $result['coupon']->id,
            'coupon_discount' => $result['discount_amount'],
            'platform_coupon_discount' => $result['platform_share'],
            'vendor_coupon_discount' => $result['vendor_share'],
            'coupon_data' => json_encode($result['breakdown'])
        ]);

        $this->updateCartTotals($cart);

        return response()->json([
            'success' => true,
            'message' => 'Coupon applied successfully',
            'data' => [
                'discount' => $result['discount_amount'],
                'breakdown' => $result['breakdown'],
                'totals' => $this->calculateTotals($cart)
            ]
        ]);
    }

    /* =========================
        REMOVE COUPON
    ========================= */
    public function removeCoupon(Request $request)
    {
        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Please login'
            ], 401);
        }

        $cart = Cart::where('user_id', $user->id)->first();
        
        if ($cart) {
            $cart->update([
                'coupon_id' => null,
                'coupon_discount' => 0,
                'platform_coupon_discount' => 0,
                'vendor_coupon_discount' => 0,
                'coupon_data' => null
            ]);
            $this->updateCartTotals($cart);
        }

        return response()->json([
            'success' => true,
            'message' => 'Coupon removed successfully'
        ]);
    }

  
    /* =========================
    CALCULATE TOTALS - ENHANCED (FIXED)
========================= */
public function calculateTotals(Cart $cart)
{
    $subtotal = $cart->items->sum(
        fn($item) => $item->selling_price * $item->quantity
    );

    // Group by vendor for shipping calculation
    $itemsByVendor = $cart->items->groupBy('vendor_id');
    $shippingTotal = 0;
    $taxTotal = 0;

    foreach ($itemsByVendor as $vendorId => $items) {
        // Default shipping calculation (no vendor.settings needed)
        $shippingTotal += $items->sum(fn($i) => ($i->estimated_shipping ?? 0) * $i->quantity);
        
        // Calculate tax
        foreach ($items as $item) {
            $tax = ($item->selling_price * $item->quantity * ($item->tax_rate ?? 18)) / 100;
            $taxTotal += $tax;
        }
    }

    $couponDiscount = $cart->coupon_discount ?? 0;
    $platformDiscount = $cart->platform_coupon_discount ?? 0;
    $vendorDiscount = $cart->vendor_coupon_discount ?? 0;

    $grandTotal = $subtotal + $shippingTotal + $taxTotal - $couponDiscount;

    return [
        'subtotal' => round($subtotal, 2),
        'shipping_total' => round($shippingTotal, 2),
        'tax_total' => round($taxTotal, 2),
        'coupon_discount' => round($couponDiscount, 2),
        'platform_coupon_discount' => round($platformDiscount, 2),
        'vendor_coupon_discount' => round($vendorDiscount, 2),
        'grand_total' => round($grandTotal, 2)
    ];
}

    /* =========================
        UPDATE CART TOTALS
    ========================= */
    public function updateCartTotals(Cart $cart)
    {
        $totals = $this->calculateTotals($cart);
        
        $cart->update([
            'subtotal' => $totals['subtotal'],
            'shipping_total' => $totals['shipping_total'],
            'tax_total' => $totals['tax_total'],
            'grand_total' => $totals['grand_total']
        ]);
    }

    /* =========================
        EMPTY TOTALS
    ========================= */
    protected function emptyTotals()
    {
        return [
            'subtotal' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'coupon_discount' => 0,
            'platform_coupon_discount' => 0,
            'vendor_coupon_discount' => 0,
            'grand_total' => 0
        ];
    }

    /* =========================
        HELPER METHODS
    ========================= */
    private function getStock($product, $variant = null)
    {
        if ($variant) return $variant->stock ?? 0;
        return $product->stock ?? 0;
    }

    private function getSellingPrice($product, $variant = null)
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


// Also fix estimateVendorShipping method
private function estimateVendorShipping($items, $vendor)
{
    // Simple calculation - sum of estimated shipping
    return $items->sum(fn($i) => ($i->estimated_shipping ?? 0) * $i->quantity);
}
       private function mergeGuestCart($guestToken, Cart $userCart)
{
    $guestCart = Cart::with(['items.product', 'items.variant'])
        ->where('guest_token', $guestToken)
        ->first();

    if (!$guestCart) return;

    foreach ($guestCart->items as $item) {
        $stock = $this->getStock($item->product, $item->variant);
        
        $existing = $userCart->items()->where([
            ['product_id', $item->product_id],
            ['variant_id', $item->variant_id]
        ])->first();

        if ($existing) {
            $newQty = min($existing->quantity + $item->quantity, $stock);
            $existing->update(['quantity' => $newQty]);
        } else {
            // ✅ FIX: Get vendor_id from product, fallback to null
            $vendorId = $item->product->vendor_id ?? null;
            
            $userCart->items()->create([
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'vendor_id' => $vendorId, // ✅ Use nullable vendor_id
                'quantity' => min($item->quantity, $stock),
                'mrp' => $item->mrp,
                'selling_price' => $item->selling_price,
                'price' => $item->selling_price,
                'tax_rate' => $item->tax_rate,
                'estimated_shipping' => $item->estimated_shipping,
                'shipping_mode' => $item->shipping_mode
            ]);
        }
    }

    $guestCart->items()->delete();
    $guestCart->delete();
} 
}