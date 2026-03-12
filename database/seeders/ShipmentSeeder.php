<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Shipment;
use Carbon\Carbon;

class ShipmentSeeder extends Seeder
{
    public function run()
    {
        $shipments = [
            // Order 1 shipments
            [
                'order_id' => 1,
                'vendor_id' => 1,
                'carrier' => 'FedEx',
                'tracking_number' => 'FDX123456789',
                'status' => 'delivered',
                'shipped_at' => Carbon::now()->subDay()->addHours(2),
                'delivered_at' => Carbon::now()->subDay()->addHours(12),
                'created_at' => Carbon::now()->subDay(),
                'updated_at' => Carbon::now(),
            ],
            [
                'order_id' => 1,
                'vendor_id' => 1,
                'carrier' => 'FedEx',
                'tracking_number' => 'FDX123456790',
                'status' => 'delivered',
                'shipped_at' => Carbon::now()->subDay()->addHours(2),
                'delivered_at' => Carbon::now()->subDay()->addHours(12),
                'created_at' => Carbon::now()->subDay(),
                'updated_at' => Carbon::now(),
            ],
            
            // Order 2 shipments
            [
                'order_id' => 2,
                'vendor_id' => 2,
                'carrier' => 'BlueDart',
                'tracking_number' => 'BD987654321',
                'status' => 'shipped',
                'shipped_at' => Carbon::now()->addHours(2),
                'delivered_at' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'order_id' => 2,
                'vendor_id' => 2,
                'carrier' => 'BlueDart',
                'tracking_number' => 'BD987654322',
                'status' => 'shipped',
                'shipped_at' => Carbon::now()->addHours(2),
                'delivered_at' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        foreach ($shipments as $shipment) {
            Shipment::create($shipment);
        }
    }
}