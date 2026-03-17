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

    public function getAddressById(Request $request,int $id){
        $address=Address::findOrFail($id);

        return response()->json($address);
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
