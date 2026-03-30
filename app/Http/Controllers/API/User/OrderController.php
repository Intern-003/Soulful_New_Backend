<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use App\Models\Cart;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\OrderStatusHistory; // ✅ ADDED

class OrderController extends Controller
{

    // GET /orders
    public function index(Request $request)
    {
        $orders = Order::with(['items.product', 'address'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }


    // GET /orders/{id}
    public function show(Request $request, $id)
    {
        $order = Order::with([
            'items.product',
            'items.variant',
            'items.vendor',
            'address',
            'payments',
            'shipments'
        ])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);


        return response()->json([
            'success' => true,
            'data' => $order
        ]);

    }


    // GET /orders/{id}/track
    public function track(Request $request, $id)
    {
        $order = Order::select('id', 'order_number', 'order_status')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'tracking_status' => $order->order_status
        ]);
    }


    // GET /orders/{id}/shipment
    public function shipment(Request $request, $id)
    {
        $order = Order::with('shipments')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'shipments' => $order->shipments
        ]);
    }


    // GET /orders/{id}/tracking
    public function tracking(Request $request, $id)
    {
        $order = Order::with('shipments')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'tracking' => $order->shipments
        ]);
    }


    // GET /orders/{id}/invoice
    public function invoice(Request $request, $id)
    {
        //dd($id);
        $order = Order::with([
            'items.product',
            'items.vendor',
            'address'
        ])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'order_number' => $order->order_number,
                'date' => $order->created_at,
                'customer' => $order->user->name ?? null,
                'address' => $order->address,
                'items' => $order->items,
                'subtotal' => $order->subtotal,
                'tax' => $order->tax,
                'shipping' => $order->shipping_cost,
                'total' => $order->total,
                'status' => $order->order_status
            ]
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User token required'
            ], 400);
        }

        $request->validate([
            'address_id' => 'required|exists:addresses,id',
            'payment_method' => 'required|string'
        ]);

        $cart = Cart::with('items.product')
            ->where('user_id', $user->id)
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Cart is empty'
            ]);
        }

        $order = null;

        DB::transaction(function () use ($cart, $user, $request, &$order) {

            $subtotal = $cart->items->sum(fn($item) => $item->product->price * $item->quantity);
            $shipping = 50;
            $tax = 0;
            $discount = 0;
            $total = $subtotal + $shipping + $tax - $discount;

            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => 'ORD-' . time() . rand(100, 999),
                'address_id' => $request->address_id,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'shipping_cost' => $shipping,
                'discount' => $discount,
                'total' => $total,
                'payment_method' => $request->payment_method,
                'payment_status' => 'pending',
                'order_status' => 'placed'
            ]);

            // ✅ ADD HISTORY
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'placed',
                'note' => 'Order placed successfully'
            ]);

            foreach ($cart->items as $item) {
                $product = Product::where('id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if (!$product) {
                    throw new \Exception("Product not found: ID {$item->product_id}");
                }

                if ($item->quantity > $product->stock) {
                    throw new \Exception("Insufficient stock for product: {$product->name}");
                }

                if (!$product->vendor_id) {
                    throw new \Exception("Product {$product->name} has no vendor assigned");
                }

                $itemTotal = $product->price * $item->quantity;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'variant_id' => $item->variant_id,
                    'vendor_id' => $product->vendor_id,
                    'quantity' => $item->quantity,
                    'price' => $product->price,
                    'total' => $itemTotal,
                    'status' => 'pending'
                ]);

                $product->decrement('stock', $item->quantity);
            }

            $cart->items()->delete();
        });

        if ($order) {
            $order->load('items.product', 'items.vendor', 'address');
        }

        return response()->json([
            'success' => true,
            'message' => 'Order placed successfully',
            'data' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'total' => $order->total,
                'status' => $order->order_status,
                'items_count' => $order->items->count()
            ]
        ], 201);
    }

    public function cancel(Request $request, $id)
    {
        $order = Order::where('user_id', $request->user()->id)
            ->findOrFail($id);

        if (!in_array($order->order_status, ['placed', 'processing'])) {
            return response()->json([
                'success' => false,
                'message' => 'Order cannot be cancelled'
            ], 400);
        }

        $order->update(['order_status' => 'cancelled']);

        // ✅ ADD HISTORY
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => 'cancelled',
            'note' => 'Order cancelled by user'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully'
        ]);
    }

    public function return(Request $request, $id)
    {
        $order = Order::where('user_id', $request->user()->id)
            ->findOrFail($id);

        if ($order->order_status !== 'delivered') {
            return response()->json([
                'success' => false,
                'message' => 'Only delivered orders can be returned'
            ], 400);
        }

        $order->update(['order_status' => 'return_requested']);

        // ✅ ADD HISTORY
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => 'return_requested',
            'note' => 'Return requested by user'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Return request submitted'
        ]);
    }

    public function exchange(Request $request, $id)
    {
        $order = Order::where('user_id', $request->user()->id)
            ->findOrFail($id);

        if ($order->order_status !== 'delivered') {
            return response()->json([
                'success' => false,
                'message' => 'Only delivered orders can be exchanged'
            ], 400);
        }

        $order->update(['order_status' => 'exchange_requested']);

        // ✅ ADD HISTORY
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => 'exchange_requested',
            'note' => 'Exchange requested by user'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Exchange request submitted'
        ]);
    }


    public function statusHistory(Request $request, $id)
    {
        $order = Order::where('user_id', $request->user()->id)
            ->with('statusHistory')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $order->statusHistory
        ]);
    }

}