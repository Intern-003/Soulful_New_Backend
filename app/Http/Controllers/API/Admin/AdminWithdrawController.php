<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WithdrawRequest;
use App\Models\VendorWallet;

class AdminWithdrawController extends Controller
{
 public function approve($id)
{
    $request = WithdrawRequest::find($id);

    if (!$request) {
        return response()->json([
            'success' => false,
            'message' => 'Withdraw request not found'
        ], 404);
    }

    // ❌ Prevent double processing
    if ($request->status !== 'pending') {
        return response()->json([
            'success' => false,
            'message' => 'Request already processed'
        ], 400);
    }

    $wallet = VendorWallet::where('vendor_id', $request->vendor_id)->first();

    if (!$wallet || $wallet->balance < $request->amount) {
        return response()->json([
            'success' => false,
            'message' => 'Insufficient wallet balance'
        ], 400);
    }

    // ✅ Deduct balance
    $wallet->balance -= $request->amount;
    $wallet->save();

    // ✅ Update status
    $request->status = 'approved';
    $request->save();

    return response()->json([
        'success' => true,
        'message' => 'Withdraw request approved'
    ]);
}

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
        'message' => 'Withdraw request rejected'
    ]);
}
}
