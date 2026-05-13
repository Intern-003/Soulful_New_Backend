<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\VendorDocument;
use App\Services\ImageUploadService;

class VendorDocumentController extends Controller
{
public function store(Request $request)
{
    $request->validate([
        'vendor_id' => 'required|exists:vendors,id',
        'document_type' => 'required|string|max:255',
        'document_number' => 'required|string|max:255',
        'document_file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:5120'
    ]);

    $file = $request->file('document_file');

    // =========================
    // CREATE DIRECTORY
    // =========================
    $destination = public_path('uploads/vendor_documents');

    if (!file_exists($destination)) {
        mkdir($destination, 0755, true);
    }

    // =========================
    // HANDLE IMAGE FILES
    // =========================
    if (in_array($file->getClientOriginalExtension(), ['jpg', 'jpeg', 'png', 'webp'])) {

        $filePath = ImageUploadService::uploadWebp(
            $file,
            'vendor_documents',
            1200,
            80
        );
    }

    // =========================
    // HANDLE PDF FILES
    // =========================
    else {

        $filename = time() . '_' . uniqid() . '.pdf';

        $file->move($destination, $filename);

        $filePath = 'uploads/vendor_documents/' . $filename;
    }

    // =========================
    // SAVE DOCUMENT
    // =========================
    $document = VendorDocument::create([
        'vendor_id' => $request->vendor_id,
        'document_type' => $request->document_type,
        'document_number' => $request->document_number,
        'document_file' => $filePath,
        'status' => 'pending'
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Document uploaded successfully',
        'data' => $document,
        'url' => asset($document->document_file)
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