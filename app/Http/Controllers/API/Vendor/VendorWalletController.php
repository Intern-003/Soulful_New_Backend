<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\VendorWallet;
use App\Models\VendorTransaction;

class VendorWalletController extends Controller
{

    // GET /vendor/wallet
    public function wallet(Request $request)
    {
        $vendor = $request->user()->vendor;

        $wallet = VendorWallet::where('vendor_id', $vendor->id)->first();

        return response()->json([
            'success' => true,
            'data' => $wallet
        ]);
    }


    // GET /vendor/wallet/transactions
    public function transactions(Request $request)
    {
        $vendor = $request->user()->vendor;

        $transactions = VendorTransaction::where('vendor_id', $vendor->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $transactions
        ]);
    }
}