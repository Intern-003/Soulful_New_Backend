<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\VendorDocument;
use App\Models\Vendor;

class AdminVendorController extends Controller
{
    
public function show($id)
{
    $vendor = Vendor::find($id);

    if (!$vendor) {
        return response()->json([
            'success' => false,
            'message' => 'Vendor not found'
        ], 404);
    }

    $documents = VendorDocument::where('vendor_id', $vendor->id)->get();

    return response()->json([
        'success' => true,
        'vendor' => $vendor->store_name,
        'documents' => $documents
    ]);
}
public function approve($id)
{
    $vendor = Vendor::find($id);

    if (!$vendor) {
        return response()->json([
            'success' => false,
            'message' => 'Vendor not found'
        ], 404);
    }

    $vendor->update([
        'status' => 'approved',
        'approved_at' => now()
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Vendor approved successfully'
    ]);
}

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
        'message' => 'Vendor rejected successfully'
    ]);
}

}
