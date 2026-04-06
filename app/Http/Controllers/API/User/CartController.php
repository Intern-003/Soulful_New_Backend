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
     * 🔁 Common method to return full cart response
     */
    private function formatCartResponse($cart, $guestToken = null)
    {
        // $cart = $cart->fresh([
        //     'items.product.images',
        //     'items.variant'
        // ]);
        $cart->loadMissing([
            'items.product.images',
            'items.variant'
        ]);

        $totals = $this->calculateTotals($cart);

        return response()->json([
            'success' => true,
            'data' => [
                'cart' => $cart,
                'totals' => $totals
            ],
            'guest_token' => $guestToken
        ]);
    }

    /**
     * 🛒 GET CART
     */
    public function getCart(Request $request)
    {
        $user = Auth::guard('sanctum')->user();
        $guestToken = $request->header('Guest-Token');

        // if (!$user && !$guestToken) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'User or Guest token required'
        //     ], 400);
        // }
        if (!$user && !$guestToken) {
            $guestToken = bin2hex(random_bytes(16));

            // ✅ CREATE CART IMMEDIATELY
            $cart = Cart::create([
                'guest_token' => $guestToken
            ]);

            return $this->formatCartResponse($cart, $guestToken);
        }

        $cart = Cart::with(['items.product.images', 'items.variant'])
            ->when($user, fn($q) => $q->where('user_id', $user->id))
            ->when(!$user && $guestToken, fn($q) => $q->where('guest_token', $guestToken))
            ->first();

        if (!$cart) {
            return response()->json([
                'success' => true,
                'data' => [
                    'cart' => ['items' => []],
                    'totals' => $this->emptyTotals()
                ],
                'guest_token' => $guestToken
            ]);
        }

        return $this->formatCartResponse($cart, $user ? null : $guestToken);
    }

    /**
     * ➕ ADD TO CART
     */
    public function addToCart(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'required|integer|min:1'
        ]);

        $user = Auth::guard('sanctum')->user();
        $guestToken = $request->header('Guest-Token');

        if ($user) {
            $cart = Cart::firstOrCreate(['user_id' => $user->id]);

            if ($guestToken) {
                $this->mergeGuestCart($guestToken, $cart);
                $cart->update([
                    'user_id' => $user->id,
                    'guest_token' => null
                ]);
            }

            $guestToken = null;
        } else {
            if (!$guestToken) {
                $guestToken = bin2hex(random_bytes(16));
            }

            $cart = Cart::firstOrCreate(['guest_token' => $guestToken]);
        }

        $product = Product::findOrFail($request->product_id);

        $item = CartItem::where([
            'cart_id' => $cart->id,
            'product_id' => $request->product_id,
            'variant_id' => $request->variant_id
        ])->first();

        if ($item) {
            $item->quantity += $request->quantity;
            $item->save();
        } else {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $request->product_id,
                'variant_id' => $request->variant_id,
                'quantity' => $request->quantity,
                'price' => $product->price
            ]);
        }

        return $this->formatCartResponse($cart, $guestToken);
    }

    /**
     * 🔄 UPDATE CART ITEM
     */
    public function updateCartItem(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        $user = Auth::guard('sanctum')->user();
        $guestToken = $request->header('Guest-Token');

        $cartItem = CartItem::where('id', $id)
            ->whereHas('cart', function ($q) use ($user, $guestToken) {
                if ($user) {
                    $q->where('user_id', $user->id);
                } elseif ($guestToken) {
                    $q->where('guest_token', $guestToken);
                }
            })
            ->firstOrFail();

        $cartItem->update(['quantity' => $request->quantity]);

        $cart = $cartItem->cart;

        return $this->formatCartResponse($cart, $guestToken);
    }

    /**
     * ❌ REMOVE ITEM
     */
    public function deleteCartItem(Request $request, $id)
    {
        $user = Auth::guard('sanctum')->user();
        $guestToken = $request->header('Guest-Token');

        $cartItem = CartItem::where('id', $id)
            ->whereHas('cart', function ($q) use ($user, $guestToken) {
                if ($user) {
                    $q->where('user_id', $user->id);
                } elseif ($guestToken) {
                    $q->where('guest_token', $guestToken);
                }
            })
            ->firstOrFail();

        $cart = $cartItem->cart;

        $cartItem->delete();

        return $this->formatCartResponse($cart, $guestToken);
    }

    /**
     * 🧹 CLEAR CART
     */
    public function clearCart(Request $request)
    {
        $user = Auth::guard('sanctum')->user();
        $guestToken = $request->header('Guest-Token');

        $cart = Cart::when($user, fn($q) => $q->where('user_id', $user->id))
            ->when(!$user && $guestToken, fn($q) => $q->where('guest_token', $guestToken))
            ->first();

        if ($cart) {
            $cart->items()->delete();
            $cart->delete();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'cart' => ['items' => []],
                'totals' => $this->emptyTotals()
            ],
            'guest_token' => $guestToken
        ]);
    }

    /**
     * 🧮 TOTALS
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

    protected function emptyTotals()
    {
        return [
            'subtotal' => 0,
            'shipping' => 0,
            'discount' => 0,
            'total' => 0
        ];
    }

    /**
     * 🔁 MERGE GUEST CART
     */
    public function mergeGuestCart($guestToken, Cart $userCart)
    {
        $guestCart = Cart::with('items')->where('guest_token', $guestToken)->first();

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

        $guestCart->items()->delete();
        $guestCart->delete();
    }
}