<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WithdrawRequest;
use Carbon\Carbon;

class WithdrawRequestSeeder extends Seeder
{
    public function run()
    {
        $requests = [
            [
                'vendor_id' => 1,
                'amount' => 5000.00,
                'status' => 'approved',
                'requested_at' => Carbon::now()->subHours(2),
                'approved_at' => Carbon::now()->subHour(),
                'created_at' => Carbon::now()->subHours(2),
                'updated_at' => Carbon::now()->subHour(),
            ],
            [
                'vendor_id' => 2,
                'amount' => 3000.00,
                'status' => 'pending',
                'requested_at' => Carbon::now()->subHour(),
                'approved_at' => null,
                'created_at' => Carbon::now()->subHour(),
                'updated_at' => Carbon::now()->subHour(),
            ],
        ];

        foreach ($requests as $request) {
            WithdrawRequest::create($request);
        }
    }
}