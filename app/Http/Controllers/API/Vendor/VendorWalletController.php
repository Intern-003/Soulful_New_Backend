<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\VendorWallet;
use App\Models\VendorTransaction;
use App\Models\WithdrawRequest;

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

       // POST /vendor/wallet/withdraw
    public function withdraw(Request $request)
    {
        $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'amount' => 'required|numeric|min:1'
        ]);

        $wallet = VendorWallet::where('vendor_id',$request->vendor_id)->first();

        if(!$wallet){
            return response()->json([
                'success' => false,
                'message' => 'Vendor wallet not found'
            ],404);
        }

        if($wallet->balance < $request->amount){
            return response()->json([
                'success' => false,
                'message' => 'Insufficient wallet balance'
            ],400);
        }

        $withdraw = WithdrawRequest::create([
            'vendor_id' => $request->vendor_id,
            'amount' => $request->amount,
            'status' => 'pending',
            'requested_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Withdraw request submitted successfully',
            'data' => $withdraw
        ],201);
    }

}