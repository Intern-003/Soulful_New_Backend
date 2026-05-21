<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WithdrawRequest;
use App\Models\VendorWallet;

class AdminWithdrawController extends Controller
{
    // Get All Withdraw Requests
    public function getWithdrawRequests(Request $request)
    {
        $query = WithdrawRequest::with(['vendor', 'user']);
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }
        
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        
        $withdraws = $query->orderBy('created_at', 'desc')->paginate(20);
        
        // Transform to include seller name and wallet balance
        $withdraws->getCollection()->transform(function ($item) {
            if ($item->vendor) {
                $item->seller_name = $item->vendor->store_name;
                $item->seller_type = 'vendor';
                $wallet = VendorWallet::where('vendor_id', $item->vendor_id)->first();
                $item->current_balance = $wallet ? $wallet->balance : 0;
            } elseif ($item->user) {
                $item->seller_name = $item->user->name;
                $item->seller_type = 'individual';
                $wallet = VendorWallet::where('user_id', $item->user_id)->first();
                $item->current_balance = $wallet ? $wallet->balance : 0;
            } else {
                $item->seller_name = 'Unknown';
                $item->seller_type = 'unknown';
                $item->current_balance = 0;
            }
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $withdraws
        ]);
    }

    // Approve Withdraw Request
    public function approve($id)
    {
        $request = WithdrawRequest::find($id);

        if (!$request) {
            return response()->json([
                'success' => false,
                'message' => 'Withdraw request not found'
            ], 404);
        }

        if ($request->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Request already processed'
            ], 400);
        }

        // Find the wallet
        $wallet = null;
        if ($request->vendor_id) {
            $wallet = VendorWallet::where('vendor_id', $request->vendor_id)->first();
        } elseif ($request->user_id) {
            $wallet = VendorWallet::where('user_id', $request->user_id)->first();
        }

        if (!$wallet || $wallet->balance < $request->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient wallet balance'
            ], 400);
        }

        // Deduct balance
        $wallet->balance -= $request->amount;
        $wallet->save();

        // Create a debit transaction record - FIXED
        \App\Models\VendorTransaction::create([
            'vendor_wallet_id' => $wallet->id,
            'vendor_id' => $request->vendor_id,
            'order_item_id' => null,
            'amount' => $request->amount,
            'commission' => 0,
            'net_amount' => $request->amount,
            'status' => 'completed',
            'type' => 'debit',
            'description' => 'Withdrawal approved - Request #' . $request->id
        ]);

        // Update status
        $request->status = 'approved';
        $request->approved_at = now();
        $request->save();

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal request approved'
        ]);
    }

    // Reject Withdraw Request
    public function reject($id)
    {
        $request = WithdrawRequest::find($id);

        if (!$request) {
            return response()->json([
                'success' => false,
                'message' => 'Withdraw request not found'
            ], 404);
        }

        if ($request->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Request already processed'
            ], 400);
        }

        $request->status = 'rejected';
        $request->save();

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal request rejected'
        ]);
    }
}