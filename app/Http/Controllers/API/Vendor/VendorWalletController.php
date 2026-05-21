<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\VendorWallet;
use App\Models\VendorTransaction;
use App\Models\WithdrawRequest;

class VendorWalletController extends Controller
{
    // Helper to get wallet owner (vendor or individual seller)
    private function getWalletOwner($user)
    {
        if ($user->vendor) {
            return [
                'type' => 'vendor',
                'id' => $user->vendor->id,
                'field' => 'vendor_id'
            ];
        }
        
        return [
            'type' => 'user',
            'id' => $user->id,
            'field' => 'user_id'
        ];
    }

    // GET /vendor/wallet
    public function wallet(Request $request)
    {
        $user = $request->user();
        $owner = $this->getWalletOwner($user);
        
        $wallet = VendorWallet::where($owner['field'], $owner['id'])->first();
        
        if (!$wallet) {
            $wallet = VendorWallet::create([
                $owner['field'] => $owner['id'],
                'balance' => 0
            ]);
        }

        // Also return total earned and pending earnings
        $totalEarned = $this->calculateTotalEarned($wallet);
        $pendingEarnings = $this->calculatePendingEarnings($wallet);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $wallet->id,
                'balance' => $wallet->balance,
                'total_earned' => $totalEarned,
                'pending_earnings' => $pendingEarnings
            ]
        ]);
    }

    // GET /vendor/wallet/transactions
    public function transactions(Request $request)
    {
        $user = $request->user();
        $owner = $this->getWalletOwner($user);
        
        $wallet = VendorWallet::where($owner['field'], $owner['id'])->first();
        
        if (!$wallet) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }
        
        $transactions = VendorTransaction::where('vendor_wallet_id', $wallet->id)
            ->with('orderItem.product')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $transactions
        ]);
    }

    // GET /vendor/withdrawals
    public function withdrawals(Request $request)
    {
        $user = $request->user();
        $owner = $this->getWalletOwner($user);
        
        $withdrawals = WithdrawRequest::where($owner['field'], $owner['id'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $withdrawals
        ]);
    }

    // POST /vendor/wallet/withdraw
    public function withdraw(Request $request)
    {
        $user = $request->user();
        $owner = $this->getWalletOwner($user);
        
        $request->validate([
            'amount' => 'required|numeric|min:100'  // Minimum ₹100
        ]);

        $wallet = VendorWallet::where($owner['field'], $owner['id'])->first();

        if (!$wallet) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet not found'
            ], 404);
        }

        if ($wallet->balance < $request->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient wallet balance'
            ], 400);
        }

        $withdraw = WithdrawRequest::create([
            $owner['field'] => $owner['id'],
            'amount' => $request->amount,
            'status' => 'pending',
            'requested_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal request submitted successfully',
            'data' => $withdraw
        ], 201);
    }

    // Helper methods
    private function calculateTotalEarned($wallet)
    {
        return VendorTransaction::where('vendor_wallet_id', $wallet->id)
            ->where('type', 'credit')
            ->where('status', 'completed')
            ->sum('net_amount');
    }

    private function calculatePendingEarnings($wallet)
    {
        return VendorTransaction::where('vendor_wallet_id', $wallet->id)
            ->where('type', 'credit')
            ->where('status', 'pending')
            ->sum('net_amount');
    }
}