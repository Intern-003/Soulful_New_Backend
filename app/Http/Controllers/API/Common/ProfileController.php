<?php

namespace App\Http\Controllers\API\Common;

use App\Http\Controllers\Controller;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage; 

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


public function deleteAvatar(Request $request)
{
    // assuming logged-in user's profile
    $profile = UserProfile::where('user_id', $request->user()->id)->first();

    if (!$profile) {
        return response()->json([
            'success' => false,
            'message' => 'Profile not found'
        ], 404);
    }

    if (!$profile->avatar) {
        return response()->json([
            'success' => false,
            'message' => 'No avatar found'
        ], 404);
    }

    // Delete avatar file
    if (Storage::exists($profile->avatar)) {
        Storage::delete($profile->avatar);
    }

    // Remove from DB
    $profile->avatar = null;
    $profile->save();

    return response()->json([
        'success' => true,
        'message' => 'Avatar deleted successfully'
    ]);
}
}
