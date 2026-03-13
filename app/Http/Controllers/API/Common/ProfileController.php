<?php

namespace App\Http\Controllers\API\Common;

use App\Http\Controllers\Controller;
use App\Models\UserProfile;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function getProfiles(Request $request){
        $profiles=UserProfile::all();
        return response()->json($profiles);
    }
    
    public function getProfileById(Request $request,int $id){
 
    $profile=UserProfile::findorfail($id);
    return response()->json([
        'success'=> true,
        'data'=> $profile
    ]);
    }
}
