<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Coupon;
use Illuminate\Support\Facades\Auth;

class VendorCouponController extends Controller
{
    // POST /vendor/coupons
    public function store(Request $request)
{
    $request->validate([
        'code' => 'required|string|unique:coupons,code',
        'type' => 'required|in:fixed,percent',
        'value' => 'required|numeric|min:0',
        'min_order_amount' => 'nullable|numeric|min:0',
        'max_discount' => 'nullable|numeric|min:0',
        'usage_limit' => 'nullable|integer|min:1',
        'start_date' => 'required|date',
        'expiry_date' => 'required|date|after:start_date'
    ]);

    $user = Auth::user();

    $vendorId = null;
    $creatorId = null;

    // ✅ STRICT vendor check
    if ($user->vendor && $user->vendor->id) {
        $vendorId = $user->vendor->id;
    } else {
        $creatorId = $user->id;
    }

    // 🚨 HARD GUARD (prevents bad DB rows like NULL/NULL)
    if (!$vendorId && !$creatorId) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid user state: no vendor or creator found'
        ], 422);
    }

    $coupon = Coupon::create([
        'vendor_id' => $vendorId,
        'creator_id' => $creatorId,
        'code' => strtoupper($request->code),
        'type' => $request->type,
        'value' => $request->value,
        'min_order_amount' => $request->min_order_amount,
        'max_discount' => $request->max_discount,
        'usage_limit' => $request->usage_limit,
        'used_count' => 0,
        'start_date' => $request->start_date,
        'expiry_date' => $request->expiry_date,
        'status' => true,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Coupon created successfully',
        'data' => $coupon
    ], 201);
}

    // PUT /vendor/coupons/{id}
    public function update(Request $request, $id)
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon not found'
            ], 404);
        }

        $user = Auth::user();



        $request->validate([
            'code' => 'sometimes|string|unique:coupons,code,' . $id,
            'type' => 'sometimes|in:fixed,percent',
            'value' => 'sometimes|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'start_date' => 'sometimes|date',
            'expiry_date' => 'sometimes|date|after:start_date',
            'status' => 'sometimes|boolean'
        ]);

        // ✅ Percentage validation
        if ($request->has('type') && $request->type === 'percent') {
            $value = $request->value ?? $coupon->value;

            if ($value > 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'Percentage cannot exceed 100'
                ], 422);
            }
        }

        $data = $request->only([
            'type',
            'value',
            'min_order_amount',
            'max_discount',
            'usage_limit',
            'start_date',
            'expiry_date',
            'status'
        ]);

        // Handle code uppercase
        if ($request->has('code')) {
            $data['code'] = strtoupper($request->code);
        }

        $coupon->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Coupon updated successfully',
            'data' => $coupon
        ]);
    }
    // DELETE /vendor/coupons/{id}
    public function destroy($id)
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon not found'
            ], 404);
        }

        $user = Auth::user();

        // // ✅ Admin bypass
        // if (!isset($user->is_admin) || !$user->is_admin) {
        //     // Vendor ownership check
        //     if ($coupon->vendor_id !== $user->vendor_id) {
        //         return response()->json([
        //             'success' => false,
        //             'message' => 'Unauthorized to delete this coupon'
        //         ], 403);
        //     }
        // }

        // Optional: Prevent deleting used coupons
        if ($coupon->used_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon already used. Disable instead of deleting.'
            ], 422);
        }

        $coupon->delete();

        return response()->json([
            'success' => true,
            'message' => 'Coupon deleted successfully'
        ]);
    }

    // Optional: Get single coupon
    public function show($id)
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon not found'
            ], 404);
        }

        $user = Auth::user();

        // ✅ Case 1: Vendor coupon
        if ($coupon->vendor_id) {
            if (!$user->vendor || $coupon->vendor_id !== $user->vendor->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
        }

        // ✅ Case 2: Admin/User created coupon
        if ($coupon->creator_id) {
            if ($coupon->creator_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $coupon
        ]);
    }


    public function index()
    {
        $user = Auth::user();

        if ($user->vendor) {
            $coupons = Coupon::where('vendor_id', $user->vendor->id)->get();
        } else {
            $coupons = Coupon::where('creator_id', $user->id)->get();
        }

        return response()->json([
            'success' => true,
            'data' => $coupons
        ]);
    }



}