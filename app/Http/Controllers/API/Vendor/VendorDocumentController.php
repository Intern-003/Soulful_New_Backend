<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\VendorDocument;

class VendorDocumentController extends Controller
{

    public function store(Request $request)
    {

        $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'document_type' => 'required|string',
            'document_number' => 'required|string',
            'document_file' => 'required|string'
        ]);

        $document = VendorDocument::create([
            'vendor_id' => $request->vendor_id,
            'document_type' => $request->document_type,
            'document_number' => $request->document_number,
            'document_file' => $request->document_file,
            'status' => 'pending'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully',
            'data' => $document
        ],201);
    }

    public function index(Request $request)
{
    $vendor = $request->user()->vendor;

    if (!$vendor) {
        return response()->json([
            'success' => false,
            'message' => 'Vendor not found'
        ], 404);
    }

    $documents = VendorDocument::where('vendor_id', $vendor->id)->latest()->get();

    return response()->json([
        'success' => true,
        'data' => $documents
    ]);
}

}