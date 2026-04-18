<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vendor;

class AdminVendorController extends Controller
{
    /**
     * Approve Vendor
     */
    public function index()
{
    try {
        $vendors = Vendor::latest()->get();

        return response()->json([
            'success' => true,
            'data' => $vendors
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}

    public function approve($id)
    {
        $vendor = Vendor::with('user')->find($id);

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }

        // Update vendor status
        $vendor->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => auth()->id()
        ]);

        // Assign vendor role to user
        if ($vendor->user) {
            $vendor->user->update([
                'role_id' => 3, // vendor role id
                'role' => 'vendor'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Vendor approved successfully',
            'data' => [
                'vendor_id' => $vendor->id,
                'status' => $vendor->status
            ]
        ]);
    }

    /**
     * Reject Vendor
     */
    public function reject(Request $request, $id)
    {
        $vendor = Vendor::find($id);

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 404);
        }

        $vendor->update([
            'status' => 'rejected'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vendor rejected successfully',
            'data' => [
                'vendor_id' => $vendor->id,
                'status' => $vendor->status
            ]
        ]);
    }
}