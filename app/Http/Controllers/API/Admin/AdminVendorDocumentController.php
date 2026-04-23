<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vendor;
use App\Models\VendorDocument;

class AdminVendorDocumentController extends Controller
{
    /**
     * Get all documents for a vendor
     */
    public function index($id)
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
            'data' => $documents
        ]);
    }

    /**
     * Verify (approve) a document
     */
    public function verify($id)
    {
        $doc = VendorDocument::find($id);

        if (!$doc) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found'
            ], 404);
        }

        // Prevent re-verification
        if ($doc->status === 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'Document already verified'
            ], 400);
        }

        $doc->update([
            'status' => 'verified',
            'verified_by' => auth()->id(),
            'verified_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document verified successfully',
            'data' => $doc
        ]);
    }

    /**
     * Reject a document
     */
    public function reject($id)
    {
        $doc = VendorDocument::find($id);

        if (!$doc) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found'
            ], 404);
        }

        // Prevent rejecting already rejected
        if ($doc->status === 'rejected') {
            return response()->json([
                'success' => false,
                'message' => 'Document already rejected'
            ], 400);
        }

        $doc->update([
            'status' => 'rejected'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document rejected successfully',
            'data' => $doc
        ]);
    }
}