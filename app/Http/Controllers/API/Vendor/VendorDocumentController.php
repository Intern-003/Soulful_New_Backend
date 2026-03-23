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
            'document_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:4096'
        ]);

        $file = $request->file('document_file');

        // original name
        $originalName = $file->getClientOriginalName();

        // filename without extension
        $name = pathinfo($originalName, PATHINFO_FILENAME);

        // extension
        $extension = $file->getClientOriginalExtension();

        // unique filename (add vendor_id)
        $filename = time() . '_' . $request->vendor_id . '.' . $extension;
        // upload path
        $destination = public_path('uploads/vendor_documents');

        // create folder if not exists
        if (!file_exists($destination)) {
            mkdir($destination, 0755, true);
        }

        // move file
        $file->move($destination, $filename);

        // save in DB
        $document = VendorDocument::create([
            'vendor_id' => $request->vendor_id,
            'document_type' => $request->document_type,
            'document_number' => $request->document_number,
            'document_file' => 'uploads/vendor_documents/' . $filename,
            'status' => 'pending'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully',
            'data' => $document,
            'url' => url($document->document_file)
        ], 201);
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