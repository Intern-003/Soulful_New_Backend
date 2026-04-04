<?php

namespace App\Http\Controllers\API\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;


class VendorRegisterController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'phone' => 'nullable|string',
            'store_name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);



        // Create user with vendor role
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'role_id' => 3, // vendor
            'role' => 'vendor',
        ]);

        // Create vendor profile
        $vendor = Vendor::create([
            'user_id' => $user->id,
            'store_name' => $request->store_name,
            'store_slug' => Str::slug($request->store_name) . '-' . $user->id,
            'description' => $request->description,
            'status' => 'pending',
        ]);
        //dd($vendor);

        return response()->json([
            'success' => true,
            'message' => 'Vendor registered successfully. Awaiting admin approval.',
            'data' => $vendor
        ], 201);
    }

}