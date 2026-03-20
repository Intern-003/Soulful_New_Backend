<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Coupon;
use App\Models\Cart; // Assuming you have a Cart model
use Carbon\Carbon;

class CouponController extends Controller
{
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

        $coupon = Coupon::where('code', strtoupper($request->code))
            ->where('status', true)
            ->first();

        // Check if coupon exists
        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid coupon code'
            ], 404);
        }

        // Check vendor status (optional - if vendor needs to be active)
        if ($coupon->vendor && $coupon->vendor->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'This coupon is currently unavailable'
            ], 422);
        }

        // Check date validity
        $now = Carbon::now();
        $startDate = Carbon::parse($coupon->start_date);
        $expiryDate = Carbon::parse($coupon->expiry_date);

        if ($now->lt($startDate)) {
            return response()->json([
                'success' => false,
                'message' => 'This coupon is not active yet',
                'available_from' => $startDate->format('Y-m-d')
            ], 422);
        }

        if ($now->gt($expiryDate)) {
            return response()->json([
                'success' => false,
                'message' => 'This coupon has expired',
                'expired_on' => $expiryDate->format('Y-m-d')
            ], 422);
        }

        // Check usage limit
        if ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit) {
            return response()->json([
                'success' => false,
                'message' => 'This coupon has reached its usage limit'
            ], 422);
        }

        // Check minimum order amount
        if ($coupon->min_order_amount && $request->cart_total < $coupon->min_order_amount) {
            return response()->json([
                'success' => false,
                'message' => 'Minimum order amount of ' . $coupon->min_order_amount . ' required',
                'required_amount' => $coupon->min_order_amount,
                'current_amount' => $request->cart_total
            ], 422);
        }

        // Calculate discount amount
        $discountAmount = $this->calculateDiscount($coupon, $request->cart_total);

        return response()->json([
            'success' => true,
            'message' => 'Coupon is valid',
            'data' => [
                'coupon' => [
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                    'type' => $coupon->type,
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
            'cart_id' => 'required|exists:carts,id' // Assuming you have cart system
        ]);

        $user = $request->user();
        $coupon = Coupon::where('code', strtoupper($request->code))
            ->where('status', true)
            ->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid coupon code'
            ], 404);
        }

        // Find user's cart
        $cart = Cart::where('id', $request->cart_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart not found'
            ], 404);
        }

        // Re-validate coupon with cart total
        $validationRequest = new Request([
            'code' => $request->code,
            'cart_total' => $cart->total_amount
        ]);

        $validation = $this->validateCoupon($validationRequest);
        
        if ($validation->getStatusCode() !== 200) {
            return $validation;
        }

        $validationData = $validation->getData();

        // Apply coupon to cart
        $cart->update([
            'coupon_id' => $coupon->id,
            'discount_amount' => $validationData->data->discount->amount,
            'final_amount' => $validationData->data->cart_total->after_discount
        ]);

        // Increment used count (optional - or wait until order is placed)
        // $coupon->increment('used_count');

        return response()->json([
            'success' => true,
            'message' => 'Coupon applied successfully',
            'data' => [
                'cart' => $cart->load('items'),
                'coupon' => $coupon,
                'savings' => $validationData->data->discount->amount
            ]
        ]);
    }

    /**
     * Remove coupon from cart
     * POST /coupon/remove
     */
    public function removeCoupon(Request $request)
    {
        $request->validate([
            'cart_id' => 'required|exists:carts,id'
        ]);

        $user = $request->user();

        // Find user's cart
        $cart = Cart::where('id', $request->cart_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart not found'
            ], 404);
        }

        // Check if coupon is applied
        if (!$cart->coupon_id) {
            return response()->json([
                'success' => false,
                'message' => 'No coupon applied to this cart'
            ], 422);
        }

        // Get coupon before removing
        $coupon = Coupon::find($cart->coupon_id);

        // Remove coupon from cart
        $cart->update([
            'coupon_id' => null,
            'discount_amount' => 0,
            'final_amount' => $cart->total_amount
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Coupon removed successfully',
            'data' => [
                'cart' => $cart->load('items'),
                'removed_coupon' => $coupon ? [
                    'id' => $coupon->id,
                    'code' => $coupon->code
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
            'cart_total' => 'nullable|numeric|min:0'
        ]);

        $now = Carbon::now();

        $query = Coupon::where('status', true)
            ->where('start_date', '<=', $now)
            ->where('expiry_date', '>=', $now)
            ->where(function($q) {
                $q->whereNull('usage_limit')
                  ->orWhereRaw('used_count < usage_limit');
            });

        // If cart total provided, filter by minimum order
        if ($request->cart_total) {
            $query->where(function($q) use ($request) {
                $q->whereNull('min_order_amount')
                  ->orWhere('min_order_amount', '<=', $request->cart_total);
            });
        }

        $coupons = $query->with('vendor:id,store_name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($coupon) use ($request) {
                $discountAmount = $request->cart_total ? 
                    $this->calculateDiscount($coupon, $request->cart_total) : null;
                
                return [
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                    'type' => $coupon->type,
                    'value' => $coupon->value,
                    'description' => $this->getCouponDescription($coupon),
                    'min_order_amount' => $coupon->min_order_amount,
                    'max_discount' => $coupon->max_discount,
                    'expiry_date' => $coupon->expiry_date->format('Y-m-d'),
                    'vendor' => $coupon->vendor ? $coupon->vendor->store_name : null,
                    'potential_discount' => $discountAmount ? [
                        'amount' => $discountAmount,
                        'formatted' => number_format($discountAmount, 2)
                    ] : null
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $coupons
        ]);
    }

    /**
     * Calculate discount amount
     */
    private function calculateDiscount($coupon, $cartTotal)
    {
        if ($coupon->type === 'fixed') {
            $discount = $coupon->value;
        } else { // percent
            $discount = ($cartTotal * $coupon->value) / 100;
            
            // Apply max discount limit if exists
            if ($coupon->max_discount && $discount > $coupon->max_discount) {
                $discount = $coupon->max_discount;
            }
        }

        // Ensure discount doesn't exceed cart total
        return min($discount, $cartTotal);
    }

    /**
     * Get human-readable coupon description
     */
    private function getCouponDescription($coupon)
    {
        if ($coupon->type === 'fixed') {
            $desc = '₹' . $coupon->value . ' OFF';
        } else {
            $desc = $coupon->value . '% OFF';
        }

        if ($coupon->min_order_amount) {
            $desc .= ' on min. purchase of ₹' . $coupon->min_order_amount;
        }

        if ($coupon->max_discount && $coupon->type === 'percent') {
            $desc .= ' (up to ₹' . $coupon->max_discount . ')';
        }

        return $desc;
    }
}