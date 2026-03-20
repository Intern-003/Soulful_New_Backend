<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    /**
     * Get user or guest cart with totals
     */
    public function getCart(Request $request)
    {
        $user = Auth::guard('sanctum')->user();
        
        $guestToken = $request->header('Guest-Token');

        if (!$user && !$guestToken) {
            return response()->json([
                'success' => false,
                'message' => 'User or Guest token required'
            ], 400);
        }

        $cart = Cart::with('items.product', 'items.variant')
            ->when($user, fn($q) => $q->where('user_id', $user->id))
            ->when(!$user && $guestToken, fn($q) => $q->where('guest_token', $guestToken))
            ->first();

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart is empty'
            ]);
        }

        $totals = $this->calculateTotals($cart);

        return response()->json([
            'success' => true,
            'data' => [
                'cart' => $cart,
                'totals' => $totals
            ],
            'guest_token' => $user ? null : $guestToken // Only for guest users
        ]);
    }

    /**
     * Add product to cart (guest or logged-in user)
     */
    public function addToCart(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'required|integer|min:1'
        ]);


        $user = Auth::guard('sanctum')->user(); // manually check for authenticated user

        //dd($user);
        $guestToken = $request->header('Guest-Token');

        // ✅ Logged-in user
        if ($user) {
            $cart = Cart::firstOrCreate(['user_id' => $user->id]);

            // Merge guest cart if guest token exists
            if ($guestToken) {
                $this->mergeGuestCart($guestToken, $cart);
                $cart->user_id = $user->id;
                $cart->guest_token = null;
                $cart->touch(); // updates updated_at
            }

            $guestToken = null; // remove guest token in response
        }
        // ✅ Guest user
        else {
            if (!$guestToken) {
                $guestToken = bin2hex(random_bytes(16)); // generate new token
            }
            $cart = Cart::firstOrCreate(['guest_token' => $guestToken]);
        }

        // Get product
        $product = Product::findOrFail($request->product_id);

        // Check if item already exists
        $item = CartItem::where([
            'cart_id' => $cart->id,
            'product_id' => $request->product_id,
            'variant_id' => $request->variant_id
        ])->first();

        if ($item) {
            $item->quantity += $request->quantity;
            $item->touch();
            $item->save();
        } else {
            $item = CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $request->product_id,
                'variant_id' => $request->variant_id,
                'quantity' => $request->quantity,
                'price' => $product->price
            ]);
        }

        $cart->update([
            'coupon_id' => null,
            'discount_amount' => 0
        ]);

        $totals = $this->calculateTotals($cart->fresh('items.product'));
        return response()->json([
            'success' => true,
            'message' => 'Product added to cart',
            'data' => $item,
            'totals' => $totals,
            'guest_token' => $guestToken // Only non-null for guest users
        ]);
    }

    /**
     * Merge guest cart into logged-in user's cart
     */
    public function mergeGuestCart($guestToken, Cart $userCart)
    {
        $guestCart = Cart::with('items')
            ->where('guest_token', $guestToken)
            ->first();

        if (!$guestCart)
            return;

        foreach ($guestCart->items as $item) {
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
                    'price' => $item->price
                ]);
            }
        }

        // Delete guest cart
        $guestCart->items()->delete();
        $guestCart->delete();

        $userCart->update([
            'coupon_id' => null,
            'discount_amount' => 0
        ]);
    }

    /**
     * Calculate cart totals
     */
    protected function calculateTotals(Cart $cart)
    {
        $subtotal = $cart->items->sum(fn($item) => $item->price * $item->quantity);
        $shipping = 50;

        $discount = $cart->discount_amount ?? 0;

        return [
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'discount' => $discount,
            'total' => $subtotal + $shipping - $discount
        ];
    }

    public function clearCart(Request $request)
    {
        //dd("called");
        //$user = $request->user();
        $user = Auth::guard('sanctum')->user();
        //dd($user);
        $guestToken = $request->header('Guest-Token');

        $cart = Cart::when($user, fn($q) => $q->where('user_id', $user->id))
            ->when(!$user && $guestToken, fn($q) => $q->where('guest_token', $guestToken))
            ->first();

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart is already empty'
            ]);
        }

        $cart->items()->delete();
        $cart->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cart cleared'
        ]);
    }
    public function deleteCartItem(Request $request, $id)
    {
        $user = Auth::guard('sanctum')->user();
        ;
        $guestToken = $request->header('Guest-Token');

        $cartItem = CartItem::where('id', $id)
            ->whereHas('cart', function ($q) use ($user, $guestToken) {
                if ($user) {
                    $q->where('user_id', $user->id);
                } elseif ($guestToken) {
                    $q->where('guest_token', $guestToken);
                }
            })
            ->first();

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found'
            ], 404);
        }

        $cart = $cartItem->cart;

        $cartItem->delete();

        $cart->update([
            'coupon_id' => null,
            'discount_amount' => 0
        ]);

        $totals = $this->calculateTotals($cart->fresh('items.product'));
        return response()->json([
            'success' => true,
            'message' => 'Cart item deleted',
            'totals' => $totals
        ]);
    }
    public function updateCartItem(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        $user = Auth::guard('sanctum')->user();
        $guestToken = $request->header('Guest-Token');

        // Find cart item for user or guest
        $cartItem = CartItem::where('id', $id)
            ->whereHas('cart', function ($q) use ($user, $guestToken) {
                if ($user) {
                    $q->where('user_id', $user->id);
                } elseif ($guestToken) {
                    $q->where('guest_token', $guestToken);
                }
            })
            ->first();

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found'
            ], 404);
        }

        $cartItem->quantity = $request->quantity;
        $cartItem->save();
        $cart = $cartItem->cart;
        $cart->update([
            'coupon_id' => null,
            'discount_amount' => 0
        ]);

        $totals = $this->calculateTotals($cart->fresh('items.product'));
        return response()->json([
            'success' => true,
            'message' => 'Cart item updated',
            'data' => $cartItem,
            'totals' => $totals
        ]);
    }
}