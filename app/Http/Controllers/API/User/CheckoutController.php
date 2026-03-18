<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;

class CheckoutController extends Controller
{
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

        $cart = Cart::with('items.product', 'items.variant')
            ->firstOrCreate(['user_id' => $user->id]);

        // Merge guest cart if exists
        if ($guestToken) {
            app(CartController::class)->mergeGuestCart($guestToken, $cart);
        }

        $totals = app(CartController::class)->calculateTotals($cart);

        return response()->json([
            'success' => true,
            'data' => [
                'cart' => $cart,
                'totals' => $totals
            ]
        ]);
    }

    public function validateCheckout(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User token required'], 400);
        }

        $cart = Cart::with('items.product')->where('user_id', $user->id)->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Cart is empty']);
        }

        $errors = [];
        foreach ($cart->items as $item) {
            $product = $item->product;

            if (!$product) {
                $errors[] = "Product ID {$item->product_id} no longer exists.";
                continue;
            }

            if ($item->quantity > $product->stock) {
                $errors[] = "{$product->name} only has {$product->stock} items available.";
            }

            if ($item->price != $product->price) {
                $item->price = $product->price;
                $item->save();
                $errors[] = "{$product->name} price updated to {$product->price}.";
            }
        }

        if (!empty($errors)) {
            return response()->json(['success' => false, 'message' => 'Checkout validation failed', 'errors' => $errors]);
        }

        return response()->json(['success' => true, 'message' => 'Cart is valid for checkout']);
    }

    public function checkout(Request $request)
{
    $request->validate([
        'address_id' => 'required|exists:addresses,id',
        'payment_method' => 'sometimes|in:cod,card,bank_transfer,wallet'
    ]);

    $user = $request->user();

    if (!$user) {
        return response()->json(['success' => false, 'message' => 'User token required'], 400);
    }

    // Verify address belongs to user
    $address = $user->addresses()->find($request->address_id);
    if (!$address) {
        return response()->json([
            'success' => false, 
            'message' => 'Invalid address selected'
        ], 400);
    }

    $cart = Cart::with('items.product', 'items.variant')->where('user_id', $user->id)->first();
    if (!$cart || $cart->items->isEmpty()) {
        return response()->json(['success' => false, 'message' => 'Cart is empty']);
    }

    $order = null;
    
    DB::transaction(function () use ($request, $cart, $user, &$order) {
        $subtotal = $cart->items->sum(fn($item) => $item->product->price * $item->quantity);
        $shipping = 50;
        $tax = 0;
        $discount = 0;
        $total = $subtotal + $shipping + $tax - $discount;

        // Create the order
        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'ORD-' . time() . rand(100, 999),
            'address_id' => $request->address_id,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'shipping_cost' => $shipping,
            'total' => $total,
            'payment_method' => $request->payment_method ?? 'cod',
            'payment_status' => 'pending',
            'order_status' => 'placed',
        ]);

        // Create order items
        foreach ($cart->items as $item) {
            $product = Product::where('id', $item->product_id)->lockForUpdate()->first();
            
            if (!$product) {
                throw new \Exception("Product not found: ID {$item->product_id}");
            }
            
            if ($item->quantity > $product->stock) {
                throw new \Exception("Insufficient stock for product: {$product->name}");
            }

            // Calculate item total
            $itemTotal = $product->price * $item->quantity;

            // Create order item with all required fields from your schema
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'variant_id' => $item->variant_id, // If cart item has variant_id
                'vendor_id' => $product->vendor_id, // Make sure your products table has vendor_id
                'price' => $product->price,
                'quantity' => $item->quantity,
                'total' => $itemTotal,
                'status' => 'pending' // Default status for order item
            ]);

            // Decrement stock
            $product->decrement('stock', $item->quantity);
        }

        // Clear the cart
        $cart->items()->delete();
    });

    // Load relationships for response
    $order->load('items.product', 'items.variant', 'address');

    return response()->json([
        'success' => true,
        'message' => 'Order placed successfully',
        'data' => [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'total' => $order->total,
            'status' => $order->order_status,
            'items_count' => $order->items->count()
        ]
    ]);
}
}