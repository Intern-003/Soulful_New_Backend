<?php

namespace App\Http\Controllers\API\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Address;

class AddressController extends Controller
{
    public function getAddresses(Request $request){
        $addresses=Address::all();

        return response()->json($addresses);
    }

    public function getAddress(Request $request)
{
    $user = auth()->user();

    $addresses = Address::where('user_id', $user->id)->get();

    return response()->json($addresses);
}

    public function store(Request $request){
        $user=auth()->user();

        $validated=$request->validate([
            'name'=> 'required|string|max:255',
            'phone'=>'required|string|max:15',
            'address_line1'=>'required|string',
            'address_line2'=>'nullable|string',
            'city'=>'required|string',
            'state'=>'required|string',
            'country'=>'required|string',
            'postal_code'=>'required|string',
            'is_default'=>'nullable|boolean',
            ]);

            //If 
            if(!empty($validated['is_default']) && $validated['is_default']){
                Address::where('user_id',$user->id)
                    ->update(['is_default'=>false]);
            }

            $address=Address::create([
                'user_id'=>$user->id,
                ...$validated
            ]);

            return response()->json([
                'message'=> "Address added successfully",
                'data'=>$address
            ],201);
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
    
}
