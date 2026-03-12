<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Payment;
use Carbon\Carbon;

class PaymentSeeder extends Seeder
{
    public function run()
    {
        $payments = [
            [
                'order_id' => 1,
                'payment_gateway' => 'stripe',
                'transaction_id' => 'ch_123456789',
                'amount' => 108989.00,
                'status' => 'success',
                'paid_at' => Carbon::now()->subDay(),
                'created_at' => Carbon::now()->subDay(),
                'updated_at' => Carbon::now()->subDay(),
            ],
            [
                'order_id' => 2,
                'payment_gateway' => 'paypal',
                'transaction_id' => 'PAY-123456789',
                'amount' => 8148.00,
                'status' => 'success',
                'paid_at' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'order_id' => 3,
                'payment_gateway' => 'razorpay',
                'transaction_id' => 'RP-987654321',
                'amount' => 11879.00,
                'status' => 'pending',
                'paid_at' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        foreach ($payments as $payment) {
            Payment::create($payment);
        }
    }
}