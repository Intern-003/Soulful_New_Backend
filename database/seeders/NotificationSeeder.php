<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Notification;
use Carbon\Carbon;

class NotificationSeeder extends Seeder
{
    public function run()
    {
        $notifications = [
            // User 2 notifications
            [
                'user_id' => 2,
                'title' => 'Order Confirmed',
                'message' => 'Your order ORD-2026-0001 has been confirmed',
                'type' => 'order',
                'read_at' => null,
                'created_at' => Carbon::now()->subDay(),
                'updated_at' => Carbon::now()->subDay(),
            ],
            [
                'user_id' => 2,
                'title' => 'Order Shipped',
                'message' => 'Your order has been shipped via FedEx',
                'type' => 'order',
                'read_at' => Carbon::now()->subDay()->addHours(3),
                'created_at' => Carbon::now()->subDay()->addHours(2),
                'updated_at' => Carbon::now()->subDay()->addHours(2),
            ],
            [
                'user_id' => 2,
                'title' => 'Order Delivered',
                'message' => 'Your order ORD-2026-0001 has been delivered',
                'type' => 'order',
                'read_at' => Carbon::now()->subDay()->addHours(13),
                'created_at' => Carbon::now()->subDay()->addHours(12),
                'updated_at' => Carbon::now()->subDay()->addHours(12),
            ],
            
            // User 3 notifications
            [
                'user_id' => 3,
                'title' => 'Order Processing',
                'message' => 'Your order ORD-2026-0002 is being processed',
                'type' => 'order',
                'read_at' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'user_id' => 3,
                'title' => 'Order Shipped',
                'message' => 'Your order has been shipped via BlueDart',
                'type' => 'order',
                'read_at' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            
            // Vendor notifications
            [
                'user_id' => 4,
                'title' => 'New Order',
                'message' => 'You have received a new order #ORD-2026-0001',
                'type' => 'vendor',
                'read_at' => Carbon::now()->subDay(),
                'created_at' => Carbon::now()->subDay(),
                'updated_at' => Carbon::now()->subDay(),
            ],
            [
                'user_id' => 5,
                'title' => 'New Order',
                'message' => 'You have received a new order #ORD-2026-0002',
                'type' => 'vendor',
                'read_at' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        foreach ($notifications as $notification) {
            Notification::create($notification);
        }
    }
}