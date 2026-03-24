<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vendor;
use App\Models\VendorDocument;

class AdminVendorDocumentController extends Controller
{
    //
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
}
