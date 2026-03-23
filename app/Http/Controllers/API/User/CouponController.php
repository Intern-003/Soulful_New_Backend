<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Coupon;
use App\Models\Cart;
use Carbon\Carbon;

class CouponController extends Controller
{
    /**
     * Validate coupon logic (reusable)
     */
    public function validateCouponLogic($coupon, $cartTotal)
    {
        if (!$coupon || !$coupon->status) {
            return ['success' => false, 'message' => 'Invalid coupon'];
        }

        $now = Carbon::now();

        if ($coupon->start_date > $now) {
            return ['success' => false, 'message' => 'Coupon not started'];
        }

        if ($coupon->expiry_date < $now) {
            return ['success' => false, 'message' => 'Coupon expired'];
        }

        if ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit) {
            return ['success' => false, 'message' => 'Coupon usage limit reached'];
        }

        if ($coupon->min_order_amount && $cartTotal < $coupon->min_order_amount) {
            return ['success' => false, 'message' => 'Minimum order not met'];
        }

        if ($coupon->vendor && $coupon->vendor->status !== 'active') {
            return ['success' => false, 'message' => 'This coupon is currently unavailable'];
        }

        return ['success' => true];
    }

    /**
     * Calculate discount (reusable)
     * Now supports both 'percent' and 'percentage' types
     */
    public function calculateDiscount($coupon, $cartTotal)
    {
        // Check if it's a percentage type (supports both 'percent' and 'percentage')
        $isPercentage = in_array($coupon->type, ['percent', 'percentage']);
        
        if ($isPercentage) {
            $discount = ($cartTotal * $coupon->value) / 100;

            if ($coupon->max_discount && $discount > $coupon->max_discount) {
                $discount = $coupon->max_discount;
            }
        } else { // fixed
            $discount = $coupon->value;
        }

        return min($discount, $cartTotal);
    }

    /**
     * Get coupon type display name
     */
    private function getCouponTypeDisplay($type)
    {
        if (in_array($type, ['percent', 'percentage'])) {
            return 'percentage';
        }
        return 'fixed';
    }

    /**
     * Validate coupon without applying
     * POST /coupon/validate
     */
    public function validateCoupon(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'cart_total' => 'required|numeric|min:0'
        ]);

        $coupon = Coupon::where('code', strtoupper($request->code))->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid coupon code'
            ], 404);
        }

        $validation = $this->validateCouponLogic($coupon, $request->cart_total);
        if (!$validation['success']) {
            return response()->json($validation, 422);
        }

        $discountAmount = $this->calculateDiscount($coupon, $request->cart_total);

        return response()->json([
            'success' => true,
            'message' => 'Coupon is valid',
            'data' => [
                'coupon' => [
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                    'type' => $this->getCouponTypeDisplay($coupon->type), // Normalize display
                    'value' => $coupon->value,
                    'min_order_amount' => $coupon->min_order_amount,
                    'max_discount' => $coupon->max_discount
                ],
                'discount' => [
                    'amount' => $discountAmount,
                    'formatted' => number_format($discountAmount, 2)
                ],
                'cart_total' => [
                    'original' => $request->cart_total,
                    'after_discount' => $request->cart_total - $discountAmount
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

        $cart = Cart::with('items.product')
            ->where('id', $request->cart_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$cart) {
            return response()->json(['success' => false, 'message' => 'Cart not found'], 404);
        }

        $coupon = Coupon::where('code', strtoupper($request->code))->first();

        if (!$coupon) {
            return response()->json(['success' => false, 'message' => 'Invalid coupon code'], 404);
        }

        // Validate coupon logic
        $subtotal = $cart->items->sum(fn($i) => $i->price * $i->quantity);
        $validation = $this->validateCouponLogic($coupon, $subtotal);

        if (!$validation['success']) {
            return response()->json($validation, 422);
        }

        $discount = $this->calculateDiscount($coupon, $subtotal);

        $cart->update([
            'coupon_id' => $coupon->id,
            'discount_amount' => $discount
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Coupon applied',
            'discount' => $discount
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

        $cart->update(['coupon_id' => null, 'discount_amount' => 0]);

        return response()->json([
            'success' => true,
            'message' => 'Coupon removed successfully',
            'data' => [
                'cart' => $cart->load('items'),
                'removed_coupon' => $coupon ? ['id' => $coupon->id, 'code' => $coupon->code] : null
            ]
        ]);
    }

    /**
     * Get available coupons for user
     * GET /coupon/available
     */
    public function availableCoupons(Request $request)
    {
        $request->validate(['cart_total' => 'nullable|numeric|min:0']);

        $now = Carbon::now();

        $query = Coupon::where('status', true)
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
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($coupon) use ($request) {
                $discountAmount = $request->cart_total ?
                    $this->calculateDiscount($coupon, $request->cart_total) : null;

                return [
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                    'type' => $this->getCouponTypeDisplay($coupon->type),
                    'value' => $coupon->value,
                    'description' => $this->getCouponDescription($coupon),
                    'min_order_amount' => $coupon->min_order_amount,
                    'max_discount' => $coupon->max_discount,
                    'expiry_date' => $coupon->expiry_date->format('Y-m-d'),
                    'vendor' => $coupon->vendor ? $coupon->vendor->store_name : 'Global',
                    'potential_discount' => $discountAmount ? [
                        'amount' => $discountAmount,
                        'formatted' => number_format($discountAmount, 2)
                    ] : null
                ];
            });

        return response()->json(['success' => true, 'data' => $coupons]);
    }

    /**
     * Get human-readable coupon description
     */
    private function getCouponDescription($coupon)
    {
        $isPercentage = in_array($coupon->type, ['percent', 'percentage']);
        
        if ($isPercentage) {
            $desc = $coupon->value . '% OFF';
        } else {
            $desc = '₹' . $coupon->value . ' OFF';
        }

        if ($coupon->min_order_amount) {
            $desc .= ' on min. purchase of ₹' . $coupon->min_order_amount;
        }

        if ($coupon->max_discount && $isPercentage) {
            $desc .= ' (up to ₹' . $coupon->max_discount . ')';
        }

        return $desc;
    }
}