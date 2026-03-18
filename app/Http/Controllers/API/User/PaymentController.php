<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Str;


class PaymentController extends Controller
{

    public function createOrder(Request $request)
    {

       
        $user = $request->user();
     
        $request->validate([
            'order_id' => 'required|exists:orders,id'
        ]);
  
        //dd($request);
        $order = Order::where('user_id', $user->id)
            ->findOrFail($request->order_id);

    

        // Create payment record (pending)
        $payment = Payment::create([
            'order_id' => $order->id,
            'payment_gateway' => 'razorpay', // or stripe
            'transaction_id' => 'txn_' . Str::random(10),
            'amount' => $order->total,
            'status' => 'pending'
        ]);
  
        return response()->json([
            'success' => true,
            'message' => 'Payment order created',
            'data' => [
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'amount' => $payment->amount
            ]
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'payment_id' => 'required|exists:payments,id',
            'transaction_id' => 'required'
        ]);

        $payment = Payment::findOrFail($request->payment_id);

        // TODO: Verify with gateway (Razorpay/Stripe API)
        // For now assume success

        $payment->update([
            'status' => 'success',
            'transaction_id' => $request->transaction_id,
            'paid_at' => now()
        ]);

        // Update order payment status
        $payment->order->update([
            'payment_status' => 'paid',
            'order_status' => 'processing'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment verified successfully'
        ]);
    }

    public function status(Request $request, $order_id)
    {
        $payment = Payment::where('order_id', $order_id)
            ->with('order')
            ->latest()
            ->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $payment->order_id,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'paid_at' => $payment->paid_at
            ]
        ]);
    }

    public function refund(Request $request)
    {
        $request->validate([
            'payment_id' => 'required|exists:payments,id'
        ]);

        $payment = Payment::findOrFail($request->payment_id);

        if ($payment->status !== 'success') {
            return response()->json([
                'success' => false,
                'message' => 'Only successful payments can be refunded'
            ], 400);
        }

        // TODO: Call gateway refund API

        $payment->update([
            'status' => 'refunded'
        ]);

        $payment->order->update([
            'payment_status' => 'refunded',
            'order_status' => 'refunded'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment refunded successfully'
        ]);
    }

}
