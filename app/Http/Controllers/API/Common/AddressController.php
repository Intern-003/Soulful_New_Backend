<?php

namespace App\Http\Controllers\API\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Address;

class AddressController extends Controller
{
    public function getAddresses(Request $request)
    {
        $addresses = Address::all();

        return response()->json($addresses);
    }

    public function getAddress(Request $request)
    {
        $user = auth()->user();

        $addresses = Address::where('user_id', $user->id)->get();

        return response()->json($addresses);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:15',
            'address_line1' => 'required|string',
            'address_line2' => 'nullable|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'country' => 'required|string',
            'postal_code' => 'required|string',
            'is_default' => 'nullable|boolean',
        ]);

        //If 
        if (!empty($validated['is_default']) && $validated['is_default']) {
            Address::where('user_id', $user->id)
                ->update(['is_default' => false]);
        }

        $address = Address::create([
            'user_id' => $user->id,
            ...$validated
        ]);

        return response()->json([
            'message' => "Address added successfully",
            'data' => $address
        ], 201);
    }

    public function deleteAddress(Request $request, $id)
    {
        $address = Address::find($id);

        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'Address not found'
            ], 404);
        }

        // Ownership check (VERY IMPORTANT)
        if ($address->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this address'
            ], 403);
        }

        $address->delete();

        return response()->json([
            'success' => true,
            'message' => 'Address deleted successfully'
        ]);
    }

    public function updateAddress(Request $request, $id)
{
    $user = $request->user();

    // Find address
    $address = Address::find($id);

    if (!$address) {
        return response()->json([
            'success' => false,
            'message' => 'Address not found'
        ], 404);
    }

    // Ownership check (VERY IMPORTANT 🔥)
    if ($address->user_id !== $user->id) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized to update this address'
        ], 403);
    }

    
    // Validate (same as store, but sometimes optional fields allowed)
    $validated = $request->validate([
        'name'=> 'sometimes|required|string|max:255',
        'phone'=>'sometimes|required|string|max:15',
        'address_line1'=>'sometimes|required|string',
        'address_line2'=>'nullable|string',
        'city'=>'sometimes|required|string',
        'state'=>'sometimes|required|string',
        'country'=>'sometimes|required|string',
        'postal_code'=>'sometimes|required|string',
        'is_default'=>'nullable|boolean',
    ]);
//dd($validated);
    // Handle default address logic
    if (isset($validated['is_default']) && $validated['is_default']) {
        Address::where('user_id', $user->id)
            ->update(['is_default' => false]);
    }

    // Update address
    $address->update($validated);

    return response()->json([
        'success' => true,
        'message' => 'Address updated successfully',
        'data' => $address
    ]);
}
public function setDefaultAddress(Request $request, $id)
{
    $user = $request->user();
//dd($user);
    // Find address
    $address = Address::find($id);

    if (!$address) {
        return response()->json([
            'success' => false,
            'message' => 'Address not found'
        ], 404);
    }

    // Ownership check
    if ($address->user_id !== $user->id) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 403);
    }

    // Remove default from all user's addresses
    Address::where('user_id', $user->id)
        ->update(['is_default' => false]);

    // Set selected address as default
    $address->update([
        'is_default' => true
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Default address set successfully',
        'data' => $address
    ]);
}
}
