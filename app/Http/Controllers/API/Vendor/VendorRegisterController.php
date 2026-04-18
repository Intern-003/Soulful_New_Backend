<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class VendorRegisterController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'store_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'phone' => 'required|string|max:15',
        ]);

        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // 🔹 Check if already vendor
        $existingVendor = Vendor::where('user_id', $user->id)->first();

        if ($existingVendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor request already exists'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // 🔹 Create vendor
            $vendor = Vendor::create([
                'user_id' => $user->id,
                'store_name' => $request->store_name,
                'store_slug' => Str::slug($request->store_name) . '-' . $user->id,
                'description' => $request->description,
                'status' => 'pending',
            ]);

            // 🔹 Update phone
            $user->phone = $request->phone;
            $user->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vendor request submitted. Please upload documents.',
                'data' => $vendor
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}