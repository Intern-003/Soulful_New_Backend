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
        $orders = Order::with(['items.product', 'items.product.images', 'address'])
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
            'items.product.images',
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
             'items.product.images',
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

        // $request->validate([
        //     'address_id' => 'required|exists:addresses,id',
        //     'payment_method' => 'required|string'
        // ]);

        $request->validate([
            'address_id' => 'nullable|exists:addresses,id',

            'address' => 'required_without:address_id|string',
            'city' => 'required_without:address_id|string',
            'state' => 'required_without:address_id|string',
            'zip' => 'required_without:address_id|string',
            'country' => 'required_without:address_id|string',

            'name' => 'nullable|string',
            'phone' => 'required|string',



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

            $addressId = $request->address_id;

            if (!$addressId) {
                $address = \App\Models\Address::create([
                    'user_id' => $user->id,
                    'name' => $request->name ?? $user->name ?? 'Customer',
                    'phone' => $request->phone,

                    'address_line1' => $request->address,
                    'address_line2' => null,

                    'city' => $request->city,
                    'state' => $request->state,
                    'country' => $request->country,

                    'postal_code' => $request->zip,
                ]);

                $addressId = $address->id;
            }

            $subtotal = $cart->items->sum(
                fn($item) =>
                $item->product->price * $item->quantity
            );

            $shipping = 50;
            $tax = 0;
            $discount = 0;

            $total = $subtotal + $shipping + $tax - $discount;

            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => 'ORD-' . time() . rand(100, 999),
                'address_id' => $addressId,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'shipping_cost' => $shipping,
                'discount' => $discount,
                'total' => $total,
                'payment_method' => $request->payment_method,
                'payment_status' => 'pending',
                'order_status' => 'placed'
            ]);

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
                    throw new \Exception("Product not found");
                }

                if ($item->quantity > $product->stock) {
                    throw new \Exception("Only {$product->stock} items available for {$product->name}");
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'variant_id' => $item->variant_id,
                    'quantity' => $item->quantity,
                    'price' => $product->price,
                    'total' => $product->price * $item->quantity,
                    'status' => 'pending',
                     'vendor_id' => $product->vendor_id,
                    'user_id' => $product->user_id,
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
    $order = Order::with('items')
        ->where('user_id', $request->user()->id)
        ->findOrFail($id);

    if (!in_array($order->order_status, ['placed', 'processing'])) {
        return response()->json([
            'success' => false,
            'message' => 'Order cannot be cancelled'
        ], 400);
    }

    DB::transaction(function () use ($order) {

        foreach ($order->items as $item) {

            Product::where('id', $item->product_id)
                ->increment('stock', $item->quantity);
        }

        $order->update([
            'order_status' => 'cancelled'
        ]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => 'cancelled',
            'note' => 'Order cancelled by user'
        ]);
    });

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