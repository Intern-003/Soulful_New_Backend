<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vendor;

class AdminCommissionController extends Controller
{

    // POST /admin/commissions
    public function store(Request $request)
    {
        $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'commission_rate' => 'required|numeric|min:0|max:100'
        ]);

        $vendor = Vendor::findOrFail($request->vendor_id);

        $vendor->update([
            'commission_rate' => $request->commission_rate
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vendor commission updated successfully',
            'data' => $vendor
        ]);
    }

    public function updateVendorCommission(Request $request, $id)
{
    $vendor = Vendor::find($id);

    if (!$vendor) {
        return response()->json([
            'success' => false,
            'message' => 'Vendor not found'
        ], 404);
    }

    $request->validate([
        'commission_rate' => 'required|numeric|min:0|max:100'
    ]);

    $vendor->update([
        'commission_rate' => $request->commission_rate
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Vendor commission updated successfully',
        'data' => $vendor
    ]);
}

}