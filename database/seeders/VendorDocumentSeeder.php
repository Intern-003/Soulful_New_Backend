<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VendorDocument;
use Carbon\Carbon;

class VendorDocumentSeeder extends Seeder
{
    public function run()
    {
        $documents = [
            [
                'vendor_id' => 1,
                'document_type' => 'GST Certificate',
                'document_number' => '27AABCU9603R1ZM',
                'document_file' => 'docs/vendor1_gst.pdf',
                'status' => 'verified',
                'verified_by' => 1,
                'verified_at' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'vendor_id' => 1,
                'document_type' => 'PAN Card',
                'document_number' => 'ABCPK1234F',
                'document_file' => 'docs/vendor1_pan.pdf',
                'status' => 'verified',
                'verified_by' => 1,
                'verified_at' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'vendor_id' => 1,
                'document_type' => 'Business License',
                'document_number' => 'BL-2026-001',
                'document_file' => 'docs/vendor1_license.pdf',
                'status' => 'verified',
                'verified_by' => 1,
                'verified_at' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'vendor_id' => 2,
                'document_type' => 'GST Certificate',
                'document_number' => '29AABCU9603R1ZM',
                'document_file' => 'docs/vendor2_gst.pdf',
                'status' => 'verified',
                'verified_by' => 1,
                'verified_at' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'vendor_id' => 2,
                'document_type' => 'PAN Card',
                'document_number' => 'XYZPK5678G',
                'document_file' => 'docs/vendor2_pan.pdf',
                'status' => 'verified',
                'verified_by' => 1,
                'verified_at' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        foreach ($documents as $document) {
            VendorDocument::create($document);
        }
    }
}