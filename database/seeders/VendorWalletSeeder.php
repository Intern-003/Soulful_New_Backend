<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VendorWallet;
use Carbon\Carbon;

class VendorWalletSeeder extends Seeder
{
    public function run()
    {
        $wallets = [
            [
                'vendor_id' => 1,
                'balance' => 15000.00,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'vendor_id' => 2,
                'balance' => 8500.00,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        foreach ($wallets as $wallet) {
            VendorWallet::create($wallet);
        }
    }
}