<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ActivityLog;
use Carbon\Carbon;

class ActivityLogSeeder extends Seeder
{
    public function run()
    {
        $logs = [
            [
                'user_id' => 2,
                'action' => 'LOGIN',
                'description' => 'User logged in successfully',
                'ip_address' => '192.168.1.100',
                'created_at' => Carbon::now()->subDay()->addHours(2),
            ],
            [
                'user_id' => 2,
                'action' => 'ORDER_PLACED',
                'description' => 'Placed order ORD-2026-0001',
                'ip_address' => '192.168.1.100',
                'created_at' => Carbon::now()->subDay(),
            ],
            [
                'user_id' => 1,
                'action' => 'PRODUCT_APPROVED',
                'description' => 'Approved product ID 1',
                'ip_address' => '192.168.1.101',
                'created_at' => Carbon::now()->subDay(),
            ],
            [
                'user_id' => 4,
                'action' => 'PRODUCT_ADDED',
                'description' => 'Added new product Samsung S23',
                'ip_address' => '192.168.1.102',
                'created_at' => Carbon::now()->subDay(),
            ],
            [
                'user_id' => 3,
                'action' => 'REGISTER',
                'description' => 'New user registered',
                'ip_address' => '192.168.1.103',
                'created_at' => Carbon::now()->subDay()->subHours(5),
            ],
            [
                'user_id' => 5,
                'action' => 'LOGIN',
                'description' => 'User logged in',
                'ip_address' => '192.168.1.104',
                'created_at' => Carbon::now(),
            ],
        ];

        foreach ($logs as $log) {
            ActivityLog::create($log);
        }
    }
}