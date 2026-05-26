<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    /* =========================
        RESPONSE FORMAT
    ========================= */
    private function formatCartResponse($cart, $guestToken = null)
    {
        $cart->loadMissing([
            'items.product.images',
            'items.variant'
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'cart' => $cart,
                'totals' => $this->calculateTotals($cart)
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

    /* =========================
        ADD TO CART (FIXED)
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
        $variant = null;

        if ($request->variant_id) {
            $variant = ProductVariant::findOrFail($request->variant_id);
        }

        $stock = $this->getStock($product, $variant);

        if ($stock <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Out of stock'
            ], 422);
        }

        // cart resolve
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

        $price = $variant->price ?? $product->price;

        if ($item) {
            $item->update([
                'quantity' => $newQty,
                'price' => $price
            ]);
        } else {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'variant_id' => $request->variant_id,
                'quantity' => $request->quantity,
                'price' => $price
            ]);
        }

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

        $item->update([
            'quantity' => $request->quantity
        ]);

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

    /* =========================
        STOCK RESOLVER (IMPORTANT)
    ========================= */
    private function getStock($product, $variant = null)
    {
        if ($variant) return $variant->stock ?? 0;
        return $product->stock ?? 0;
    }

    /* =========================
        TOTALS
    ========================= */
    protected function calculateTotals(Cart $cart)
    {
        $subtotal = $cart->items->sum(
            fn($item) => $item->price * $item->quantity
        );

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

    /* =========================
        MERGE GUEST CART
    ========================= */
    public function mergeGuestCart($guestToken, Cart $userCart)
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
                $existing->update([
                    'quantity' => min($existing->quantity + $item->quantity, $stock)
                ]);
            } else {
                $userCart->items()->create([
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'quantity' => min($item->quantity, $stock),
                    'price' => $item->price
                ]);
            }
        }

        $guestCart->items()->delete();
        $guestCart->delete();
    }
}