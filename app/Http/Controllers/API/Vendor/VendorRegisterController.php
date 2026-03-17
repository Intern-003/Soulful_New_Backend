<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vendor;

class VendorRegisterController extends Controller
{

    public function register(Request $request)
    {

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'store_name' => 'required|string|max:255',
            'store_slug' => 'required|string|unique:vendors,store_slug',
            'description' => 'nullable|string'
        ]);

        $vendor = Vendor::create([
            'user_id' => $request->user_id,
            'store_name' => $request->store_name,
            'store_slug' => $request->store_slug,
            'description' => $request->description,
            'status' => 'pending'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vendor registered successfully',
            'data' => $vendor
        ],201);
    }

}