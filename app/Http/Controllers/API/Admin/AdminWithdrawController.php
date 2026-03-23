<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WithdrawRequest;
use App\Models\VendorWallet;

class AdminWithdrawController extends Controller
{

public function index(Request $request)
{
    $requests = WithdrawRequest::with([
            'vendor:id,name' // adjust fields if needed
        ])
        ->latest()
        ->paginate(10);

    return response()->json([
        'success' => true,
        'data' => $requests
    ]);
}


// ----------------------------
// Get All Withdraw Requests
// GET /admin/withdraw-requests
// ----------------------------
public function getWithdrawRequests(Request $request)
{
    $query = WithdrawRequest::query();

    // 🔍 Optional filter by status
    if ($request->has('status')) {
        $query->where('status', $request->status);
    }

    // 🔍 Optional filter by vendor
    if ($request->has('vendor_id')) {
        $query->where('vendor_id', $request->vendor_id);
    }

    // 📊 Latest first
    $withdraws = $query->orderBy('created_at', 'desc')->get();

    return response()->json([
        'success' => true,
        'data' => $withdraws
    ]);
}

// ----------------------------
// Get Single Withdraw Request
// GET /admin/withdraw-requests/{id}
// ----------------------------
public function getWithdrawRequest($id)
{
    $withdraw = WithdrawRequest::find($id);

    if (!$withdraw) {
        return response()->json([
            'success' => false,
            'message' => 'Withdraw request not found'
        ], 404);
    }

    return response()->json([
        'success' => true,
        'data' => $withdraw
    ]);
}
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
