<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;


class CheckoutController extends Controller
{
    /**
     * Get checkout summary for user or guest
     */
    public function summary(Request $request)
    {
        // Determine user
        $user = $request->user();
        $guestToken = $request->header('Guest-Token');

        if ($user) {
            // Logged-in user cart
            $cart = Cart::with('items.product', 'items.variant')
                ->firstOrCreate(['user_id' => $user->id]);

            // Merge guest cart if exists
            if ($guestToken) {
                $this->mergeGuestCart($guestToken, $cart);
            }
        } else {
            // Guest cart only
            if (!$guestToken) {
                return response()->json([
                    'success' => true,
                    'message' => 'No guest cart found',
                    'data' => []
                ]);
            }

            $cart = Cart::with('items.product', 'items.variant')
                ->where('guest_token', $guestToken)
                ->first();
        }

        if (!$cart) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'Cart is empty'
            ]);
        }

        // Calculate totals
        $totals = $this->calculateTotals($cart);

        return response()->json([
            'success' => true,
            'data' => [
                'cart' => $cart,
                'totals' => $totals
            ]
        ]);
    }

    /**
     * Merge guest cart into user cart
     */
    protected function mergeGuestCart($guestToken, Cart $userCart)
    {
        $guestCart = Cart::with('items')->where('guest_token', $guestToken)->first();
        if (!$guestCart)
            return;

        foreach ($guestCart->items as $item) {
            // Check if product+variant already exists in user cart
            $existing = $userCart->items()->where([
                ['product_id', $item->product_id],
                ['variant_id', $item->variant_id]
            ])->first();

            if ($existing) {
                $existing->quantity += $item->quantity;
                $existing->save();
            } else {
                $userCart->items()->create([
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                ]);
            }
        }

        // Delete guest cart after merge
        $guestCart->delete();
    }

    /**
     * Calculate cart totals
     */
    protected function calculateTotals(Cart $cart)
    {
        $subtotal = $cart->items->sum(function ($item) {
            return $item->price * $item->quantity;
        });

        $shipping = 50; // example flat rate
        $total = $subtotal + $shipping;

        return [
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'total' => $total
        ];
    }

    /**
     * Get all checkout data including shipping options, addresses, payment methods
     */
    public function data(Request $request)
    {
        $summary = $this->summary($request)->getData(true);

        $shippingMethods = $this->shippingMethods();

        return response()->json([
            'success' => true,
            'data' => [
                'checkout' => $summary['data'],
                'shipping_methods' => $shippingMethods,
                'payment_methods' => ['cod', 'card', 'upi'] // example
            ]
        ]);
    }

    /**
     * Available shipping methods
     */
    public function shippingMethods()
    {
        return [
            ['id' => 1, 'name' => 'Standard Shipping', 'cost' => 50],
            ['id' => 2, 'name' => 'Express Shipping', 'cost' => 100]
        ];
    }
}

