<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\VendorWallet;

class WalletController extends Controller
{

    // GET /wallet
    public function wallet(Request $request)
    {
        $user = $request->user();

        $vendor = $user->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor wallet not found'
            ]);
        }

        $wallet = VendorWallet::where('vendor_id', $vendor->id)->first();

        return response()->json([
            'success' => true,
            'data' => $wallet
        ]);
    }


    // GET /wallet/transactions
    public function transactions(Request $request)
    {
        $user = $request->user();

        $vendor = $user->vendor;

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor wallet not found'
            ]);
        }

        $wallet = VendorWallet::where('vendor_id', $vendor->id)->first();

        $transactions = $wallet
            ? $wallet->transactions()->latest()->get()
            : [];

        return response()->json([
            'success' => true,
            'data' => $transactions
        ]);
    }
}