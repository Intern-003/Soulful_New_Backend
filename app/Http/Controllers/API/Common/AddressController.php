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
}
