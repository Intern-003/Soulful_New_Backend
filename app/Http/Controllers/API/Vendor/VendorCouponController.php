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
            'vendor_id' => 'required|exists:vendors,id',
            'code' => 'required|string|unique:coupons,code',
            'type' => 'required|in:fixed,percent',
            'value' => 'required|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'start_date' => 'required|date',
            'expiry_date' => 'required|date|after:start_date'
        ]);

        $coupon = Coupon::create([
            'vendor_id' => $request->vendor_id,
            'code' => strtoupper($request->code),
            'type' => $request->type,
            'value' => $request->value,
            'min_order_amount' => $request->min_order_amount,
            'max_discount' => $request->max_discount,
            'usage_limit' => $request->usage_limit,
            'used_count' => 0,
            'start_date' => $request->start_date,
            'expiry_date' => $request->expiry_date,
            'status' => true
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

        // Check if coupon exists
        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon not found'
            ], 404);
        }

        // Ownership check - verify this coupon belongs to the vendor
        $user = Auth::user();
        // if ($coupon->vendor_id !== $user->vendor_id) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Unauthorized to update this coupon'
        //     ], 403);
        // }

        // Validate request
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

        // Additional validation for percentage value
        if ($request->has('type') && $request->type === 'percent' && $request->has('value')) {
            if ($request->value > 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'Percentage value cannot exceed 100'
                ], 422);
            }
        }

        // Prepare data for update
        $updateData = $request->only([
            'type', 'value', 'min_order_amount', 'max_discount',
            'usage_limit', 'start_date', 'expiry_date', 'status'
        ]);

        // Handle code separately to apply strtoupper
        if ($request->has('code')) {
            $updateData['code'] = strtoupper($request->code);
        }

        // Update the coupon
        $coupon->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Coupon updated successfully',
            'data' => $coupon
        ], 200);
    }

    // DELETE /vendor/coupons/{id}
    public function destroy($id)
    {
        $coupon = Coupon::find($id);

        // Check if coupon exists
        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon not found'
            ], 404);
        }

        // Ownership check
        $user = Auth::user();
        // if ($coupon->vendor_id !== $user->vendor_id) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Unauthorized to delete this coupon'
        //     ], 403);
        // }

        // Optional: Check if coupon has been used
        if ($coupon->used_count > 0) {
            // Option 1: Soft delete or just disable instead of hard delete
            // $coupon->update(['status' => false]);
            
            // Option 2: Allow delete anyway
            // Option 3: Prevent delete with message
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete coupon that has already been used. You can disable it instead.'
            ], 422);
        }

        // Delete the coupon
        $coupon->delete();

        return response()->json([
            'success' => true,
            'message' => 'Coupon deleted successfully'
        ], 200);
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
        if ($coupon->vendor_id !== $user->vendor_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $coupon
        ]);
    }

    // Optional: List all coupons for vendor
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $coupons = Coupon::where('vendor_id', $user->vendor_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $coupons
        ]);
    }
}