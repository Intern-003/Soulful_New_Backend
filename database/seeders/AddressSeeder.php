<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Address;
use Carbon\Carbon;

class AddressSeeder extends Seeder
{
    public function run()
    {
        $addresses = [
            [
                'user_id' => 2,
                'name' => 'John Customer',
                'phone' => '9876543211',
                'address_line1' => '123 Main Street',
                'address_line2' => 'Apt 4B',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'country' => 'India',
                'postal_code' => '400001',
                'is_default' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'user_id' => 2,
                'name' => 'John Customer',
                'phone' => '9876543211',
                'address_line1' => '456 Park Avenue',
                'address_line2' => null,
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'country' => 'India',
                'postal_code' => '411001',
                'is_default' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'user_id' => 3,
                'name' => 'Jane Smith',
                'phone' => '9876543212',
                'address_line1' => '789 Lake Road',
                'address_line2' => null,
                'city' => 'Bangalore',
                'state' => 'Karnataka',
                'country' => 'India',
                'postal_code' => '560001',
                'is_default' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'user_id' => 4,
                'name' => 'Tech Store',
                'phone' => '9876543213',
                'address_line1' => '321 Market Street',
                'address_line2' => 'Shop No 5',
                'city' => 'Delhi',
                'state' => 'Delhi',
                'country' => 'India',
                'postal_code' => '110001',
                'is_default' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'user_id' => 5,
                'name' => 'Fashion Hub',
                'phone' => '9876543214',
                'address_line1' => '555 Mall Road',
                'address_line2' => null,
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'country' => 'India',
                'postal_code' => '400002',
                'is_default' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        foreach ($addresses as $address) {
            Address::create($address);
        }
    }
}