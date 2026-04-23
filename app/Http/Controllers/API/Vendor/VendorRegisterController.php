<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vendor;
use App\Models\VendorDocument;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VendorRegisterController extends Controller
{
    /**
     * STEP 1
     * Create / Update Vendor Draft
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'store_name'  => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'phone'       => 'required|string|max:20',
        ]);

        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        try {
            DB::beginTransaction();

            $vendor = Vendor::where('user_id', $user->id)->first();

            /**
             * If already submitted or approved
             */
            if ($vendor && in_array($vendor->status, ['pending', 'approved'])) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Vendor request already submitted.',
                ], 422);
            }

            $payload = [
                'store_name'  => $validated['store_name'],
                'store_slug'  => Str::slug($validated['store_name']) . '-' . $user->id,
                'description' => $validated['description'] ?? null,
                'status'      => 'draft',
            ];

            /**
             * Existing draft / rejected => update
             */
            if ($vendor) {
                $vendor->update($payload);
                $vendor->refresh();
            } else {
                /**
                 * New draft create
                 */
                $payload['user_id'] = $user->id;
                $vendor = Vendor::create($payload);
            }

            /**
             * Update phone number
             */
            $user->phone = $validated['phone'];
            $user->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Business details saved successfully.',
                'data'    => $vendor,
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * STEP 3
     * Final Submit Vendor Request
     */
    public function submit(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $vendor = Vendor::where('user_id', $user->id)->first();

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor profile not found.',
            ], 404);
        }

        /**
         * Prevent duplicate requests
         */
        if (in_array($vendor->status, ['pending', 'approved'])) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor request already submitted.',
            ], 422);
        }

        /**
         * Must upload at least 1 document
         */
        $documentsCount = VendorDocument::where('vendor_id', $vendor->id)->count();

        if ($documentsCount < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Please upload at least one KYC document.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $vendor->status = 'pending';

            /**
             * Optional submitted_at column
             */
            if (Schema::hasColumn('vendors', 'submitted_at')) {
                $vendor->submitted_at = now();
            }

            $vendor->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vendor request submitted successfully.',
                'data'    => $vendor,
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}