<?php
// app/Http/Controllers/API/User/CouponController.php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Coupon;
use App\Models\Cart;
use App\Models\CartItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CouponController extends Controller
{
    /**
     * Validate coupon logic (reusable with vendor and product restrictions)
     */
    public function validateCouponLogic($coupon, $cartTotal, $cart = null)
    {
        if (!$coupon || !$coupon->status) {
            return ['success' => false, 'message' => 'Invalid coupon'];
        }

        $now = Carbon::now();

        if ($coupon->start_date > $now) {
            return ['success' => false, 'message' => 'Coupon has not started yet'];
        }

        if ($coupon->expiry_date < $now) {
            return ['success' => false, 'message' => 'Coupon has expired'];
        }

        if ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit) {
            return ['success' => false, 'message' => 'Coupon usage limit reached'];
        }

        if ($coupon->min_order_amount && $cartTotal < $coupon->min_order_amount) {
            return ['success' => false, 'message' => "Minimum order amount of ₹{$coupon->min_order_amount} required"];
        }

        // Check vendor status if coupon is vendor-specific
        if ($coupon->vendor_id) {
            if (!$coupon->vendor || $coupon->vendor->status !== 'active') {
                return ['success' => false, 'message' => 'This coupon is currently unavailable'];
            }
        }

        // Check applicable products/categories
        if ($cart && !$this->isCouponApplicableToCart($coupon, $cart)) {
            return ['success' => false, 'message' => 'This coupon is not applicable to items in your cart'];
        }

        return ['success' => true];
    }

    /**
     * Check if coupon is applicable to cart items
     */
    private function isCouponApplicableToCart($coupon, $cart)
    {
        if ($coupon->applies_to === 'all') {
            return true;
        }

        if ($coupon->applies_to === 'vendor') {
            return $cart->items->contains('vendor_id', $coupon->vendor_id);
        }

        if ($coupon->applies_to === 'category') {
            return $cart->items->contains(function ($item) use ($coupon) {
                return $item->product->category_id === $coupon->category_id;
            });
        }

        if ($coupon->applies_to === 'product') {
            return $cart->items->contains('product_id', $coupon->product_id);
        }

        return true;
    }

    /**
     * Calculate discount with split logic
     */
    public function calculateDiscount($coupon, $cartTotal, $cart = null)
    {
        if ($coupon->type === 'percent' || $coupon->type === 'percentage') {
            $discount = ($cartTotal * $coupon->value) / 100;

            if ($coupon->max_discount && $discount > $coupon->max_discount) {
                $discount = $coupon->max_discount;
            }
        } else {
            $discount = $coupon->value;
        }

        return min($discount, $cartTotal);
    }

    /**
     * Calculate coupon split between admin and vendor
     */
    public function calculateCouponSplit($discountAmount, $coupon, $cart = null)
    {
        if ($coupon->funded_by === 'admin') {
            return [
                'vendor_share' => 0,
                'admin_share' => $discountAmount,
                'breakdown' => [
                    'funded_by' => 'admin',
                    'description' => 'Fully funded by platform'
                ]
            ];
        }

        if ($coupon->funded_by === 'vendor') {
            return [
                'vendor_share' => $discountAmount,
                'admin_share' => 0,
                'breakdown' => [
                    'funded_by' => 'vendor',
                    'vendor_name' => $coupon->vendor ? $coupon->vendor->store_name : 'Vendor',
                    'description' => 'Fully funded by vendor'
                ]
            ];
        }

        // Shared coupon
        $vendorShare = ($coupon->vendor_share_percentage / 100) * $discountAmount;
        $adminShare = $discountAmount - $vendorShare;

        return [
            'vendor_share' => round($vendorShare, 2),
            'admin_share' => round($adminShare, 2),
            'breakdown' => [
                'funded_by' => 'shared',
                'vendor_percentage' => $coupon->vendor_share_percentage,
                'admin_percentage' => $coupon->admin_share_percentage,
                'description' => "Shared coupon: {$coupon->vendor_share_percentage}% vendor, {$coupon->admin_share_percentage}% platform"
            ]
        ];
    }

    /**
     * Validate coupon without applying
     * POST /coupon/validate
     */
    public function validateCoupon(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'cart_total' => 'required|numeric|min:0',
            'cart_id' => 'nullable|exists:carts,id'
        ]);

        $coupon = Coupon::where('code', strtoupper($request->code))->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid coupon code'
            ], 404);
        }

        $cart = null;
        if ($request->cart_id) {
            $cart = Cart::with('items.product')->find($request->cart_id);
        }

        $validation = $this->validateCouponLogic($coupon, $request->cart_total, $cart);
        if (!$validation['success']) {
            return response()->json($validation, 422);
        }

        $discountAmount = $this->calculateDiscount($coupon, $request->cart_total);
        $split = $this->calculateCouponSplit($discountAmount, $coupon, $cart);

        return response()->json([
            'success' => true,
            'message' => 'Coupon is valid',
            'data' => [
                'coupon' => [
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                    'type' => $coupon->type,
                    'value' => $coupon->value,
                    'funded_by' => $coupon->funded_by,
                    'vendor_share_percentage' => $coupon->vendor_share_percentage,
                    'admin_share_percentage' => $coupon->admin_share_percentage,
                    'min_order_amount' => $coupon->min_order_amount,
                    'max_discount' => $coupon->max_discount,
                    'expiry_date' => $coupon->expiry_date->format('Y-m-d')
                ],
                'discount' => [
                    'amount' => $discountAmount,
                    'formatted' => '₹' . number_format($discountAmount, 2),
                    'split' => $split
                ],
                'cart_total' => [
                    'original' => $request->cart_total,
                    'after_discount' => $request->cart_total - $discountAmount,
                    'formatted_after' => '₹' . number_format($request->cart_total - $discountAmount, 2)
                ]
            ]
        ]);
    }

    /**
     * Apply coupon to cart
     * POST /coupon/apply
     */
    public function applyCoupon(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'cart_id' => 'required|exists:carts,id'
        ]);

        $user = $request->user();

        $cart = Cart::with(['items.product', 'items.product.vendor'])
            ->where('id', $request->cart_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$cart) {
            return response()->json(['success' => false, 'message' => 'Cart not found'], 404);
        }

        if ($cart->items->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Cart is empty'], 422);
        }

        $coupon = Coupon::where('code', strtoupper($request->code))->first();

        if (!$coupon) {
            return response()->json(['success' => false, 'message' => 'Invalid coupon code'], 404);
        }

        // Calculate cart subtotal (use selling_price from cart items)
        $subtotal = $cart->items->sum(fn($i) => ($i->selling_price ?? $i->price) * $i->quantity);

        $validation = $this->validateCouponLogic($coupon, $subtotal, $cart);

        if (!$validation['success']) {
            return response()->json($validation, 422);
        }

        $discountAmount = $this->calculateDiscount($coupon, $subtotal);
        $split = $this->calculateCouponSplit($discountAmount, $coupon, $cart);

        // Update cart with coupon details
        $cart->update([
            'coupon_id' => $coupon->id,
            'coupon_discount' => $discountAmount,
            'platform_coupon_discount' => $split['admin_share'],
            'vendor_coupon_discount' => $split['vendor_share'],
            'coupon_data' => json_encode([
                'coupon_code' => $coupon->code,
                'type' => $coupon->type,
                'value' => $coupon->value,
                'funded_by' => $coupon->funded_by,
                'split' => $split['breakdown']
            ])
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Coupon applied successfully',
            'data' => [
                'discount' => [
                    'amount' => $discountAmount,
                    'formatted' => '₹' . number_format($discountAmount, 2),
                    'split' => $split
                ],
                'cart_total' => [
                    'original' => $subtotal,
                    'after_discount' => $subtotal - $discountAmount
                ]
            ]
        ]);
    }

    /**
     * Remove coupon from cart
     * POST /coupon/remove
     */
    public function removeCoupon(Request $request)
    {
        $request->validate(['cart_id' => 'required|exists:carts,id']);

        $user = $request->user();

        $cart = Cart::where('id', $request->cart_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$cart) {
            return response()->json(['success' => false, 'message' => 'Cart not found'], 404);
        }

        if (!$cart->coupon_id) {
            return response()->json(['success' => false, 'message' => 'No coupon applied to this cart'], 422);
        }

        $coupon = Coupon::find($cart->coupon_id);

        $cart->update([
            'coupon_id' => null,
            'coupon_discount' => 0,
            'platform_coupon_discount' => 0,
            'vendor_coupon_discount' => 0,
            'coupon_data' => null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Coupon removed successfully',
            'data' => [
                'removed_coupon' => $coupon ? [
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                    'funded_by' => $coupon->funded_by
                ] : null
            ]
        ]);
    }

    /**
     * Get available coupons for user
     * GET /coupon/available
     */
    public function availableCoupons(Request $request)
    {
        $request->validate([
            'cart_total' => 'nullable|numeric|min:0',
            'cart_id' => 'nullable|exists:carts,id'
        ]);

        $now = Carbon::now();
        $cart = null;

        if ($request->cart_id) {
            $cart = Cart::with('items.product')->find($request->cart_id);
        }

        // ✅ FIXED: Use correct column names from your database
        $query = Coupon::where('status', true)
            ->where('show_on_listing', true)
            ->where('start_date', '<=', $now)
            ->where('expiry_date', '>=', $now)
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                    ->orWhereRaw('used_count < usage_limit');
            })
            ->where(function ($q) {
                // Allow coupons with no vendor OR vendor is active
                $q->whereNull('vendor_id')
                    ->orWhereHas('vendor', function ($q) {
                        $q->where('status', 'active');
                    });
            });

        if ($request->cart_total) {
            $query->where(function ($q) use ($request) {
                $q->whereNull('min_order_amount')
                    ->orWhere('min_order_amount', '<=', $request->cart_total);
            });
        }

        $coupons = $query->with('vendor:id,store_name,status')
            ->orderByRaw("CASE WHEN funded_by = 'admin' THEN 1 WHEN funded_by = 'shared' THEN 2 ELSE 3 END")
            ->orderBy('value', 'desc')
            ->get()
            ->map(function ($coupon) use ($request, $cart) {
                $discountAmount = $request->cart_total ?
                    $this->calculateDiscount($coupon, $request->cart_total) : null;

                $isApplicable = true;
                if ($cart && $discountAmount) {
                    $validation = $this->validateCouponLogic($coupon, $request->cart_total, $cart);
                    $isApplicable = $validation['success'];
                }

                return [
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                    'type' => $coupon->type,
                    'value' => $coupon->value,
                    'funded_by' => $coupon->funded_by,
                    'description' => $this->getCouponDescription($coupon),
                    'min_order_amount' => $coupon->min_order_amount,
                    'max_discount' => $coupon->max_discount,
                    'expiry_date' => $coupon->expiry_date->format('Y-m-d'),
                    'days_left' => now()->diffInDays($coupon->expiry_date, false),
                    'vendor' => $coupon->vendor ? [
                        'id' => $coupon->vendor->id,
                        'name' => $coupon->vendor->store_name
                    ] : null,
                    'is_applicable' => $isApplicable,
                    'potential_discount' => $discountAmount ? [
                        'amount' => $discountAmount,
                        'formatted' => '₹' . number_format($discountAmount, 2)
                    ] : null
                ];
            });

        // Separate applicable and non-applicable coupons
        $applicable = $coupons->filter(fn($c) => $c['is_applicable'])->values();
        $notApplicable = $coupons->filter(fn($c) => !$c['is_applicable'])->values();

        return response()->json([
            'success' => true,
            'data' => [
                'applicable' => $applicable,
                'not_applicable' => $notApplicable,
                'total_available' => $coupons->count()
            ]
        ]);
    }

    /**
     * Get coupon by code (for verification)
     * GET /coupon/{code}
     */
    public function show($code)
    {
        $coupon = Coupon::with('vendor')
            ->where('code', strtoupper($code))
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'type' => $coupon->type,
                'value' => $coupon->value,
                'funded_by' => $coupon->funded_by,
                'min_order_amount' => $coupon->min_order_amount,
                'max_discount' => $coupon->max_discount,
                'usage_limit' => $coupon->usage_limit,
                'used_count' => $coupon->used_count,
                'status' => $coupon->status,
                'start_date' => $coupon->start_date->format('Y-m-d H:i:s'),
                'expiry_date' => $coupon->expiry_date->format('Y-m-d H:i:s'),
                'vendor' => $coupon->vendor ? [
                    'id' => $coupon->vendor->id,
                    'name' => $coupon->vendor->store_name
                ] : null
            ]
        ]);
    }

    /**
     * Get human-readable coupon description
     */
    private function getCouponDescription($coupon)
    {
        if ($coupon->type === 'percent') {
            $desc = $coupon->value . '% OFF';
        } else {
            $desc = '₹' . $coupon->value . ' OFF';
        }

        if ($coupon->min_order_amount && $coupon->min_order_amount > 0) {
            $desc .= ' on min. purchase of ₹' . $coupon->min_order_amount;
        }

        if ($coupon->max_discount && $coupon->type === 'percent') {
            $desc .= ' (up to ₹' . $coupon->max_discount . ')';
        }

        // Add funding source info
        if ($coupon->funded_by === 'admin') {
            $desc .= ' • Platform offer';
        } elseif ($coupon->funded_by === 'vendor' && $coupon->vendor) {
            $desc .= ' • ' . $coupon->vendor->store_name . ' offer';
        } elseif ($coupon->funded_by === 'shared') {
            $desc .= ' • Shared offer';
        }

        return $desc;
    }

    /**
     * Get coupon usage history for user
     * GET /coupon/history
     */
    public function usageHistory(Request $request)
    {
        $user = $request->user();

        $usages = \App\Models\CouponUsage::with('coupon')
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $usages->map(function ($usage) {
                return [
                    'id' => $usage->id,
                    'coupon_code' => $usage->coupon->code,
                    'discount_amount' => $usage->discount_amount,
                    'order_id' => $usage->order_id,
                    'used_at' => $usage->created_at->format('Y-m-d H:i:s'),
                    'breakdown' => $usage->breakdown
                ];
            }),
            'pagination' => [
                'current_page' => $usages->currentPage(),
                'last_page' => $usages->lastPage(),
                'per_page' => $usages->perPage(),
                'total' => $usages->total()
            ]
        ]);
    }
}