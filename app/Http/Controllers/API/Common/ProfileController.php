<?php

namespace App\Http\Controllers\API\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserProfile;
use App\Services\ImageUploadService;

class ProfileController extends Controller
{
    // ----------------------------
    // Get Profile (User + Profile)
    // ----------------------------
    public function getProfile(Request $request)
    {
        $user = $request->user()->load('role', 'profile');

        return response()->json([
            'user' => $user
        ]);
    }

    // ----------------------------
    // Update Profile
    // ----------------------------
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:15',

            'gender' => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            'bio' => 'nullable|string|max:500',
        ]);

        // Update user table
        $user->update($request->only('name', 'phone'));

        // Update or create profile
        $profile = UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            $request->only('gender', 'date_of_birth', 'bio')
        );

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user,
            'profile' => $profile
        ]);
    }

    // ----------------------------
    // Upload Avatar
    // ----------------------------
// ----------------------------
// Upload Avatar
// ----------------------------
public function uploadAvatar(Request $request)
{
    $request->validate([
        'avatar' => 'required|image|mimes:jpeg,png,jpg,webp|max:4096',
    ]);

    $user = $request->user();

    // Create profile if not exists
    $profile = UserProfile::firstOrCreate([
        'user_id' => $user->id
    ]);

    // ================= WEBP AVATAR UPLOAD =================
    if ($request->hasFile('avatar')) {

        // Delete old avatar
        if ($profile->avatar) {

            ImageUploadService::deleteImage($profile->avatar);
        }

        // Upload new avatar
        $profile->avatar = ImageUploadService::uploadWebp(
            $request->file('avatar'),
            'avatars',
            500,
            80
        );

        $profile->save();
    }

    return response()->json([
        'success' => true,
        'message' => 'Avatar uploaded successfully',
        'avatar_url' => asset($profile->avatar)
    ]);
}

    // ----------------------------
    // Delete Avatar
    // ----------------------------
// ----------------------------
// Delete Avatar
// ----------------------------
public function deleteAvatar(Request $request)
{
    $user = $request->user();

    $profile = $user->profile;

    if (!$profile || !$profile->avatar) {

        return response()->json([
            'success' => false,
            'message' => 'No avatar found'
        ], 404);
    }

    // Delete avatar image
    ImageUploadService::deleteImage($profile->avatar);

    // Remove DB value
    $profile->avatar = null;
    $profile->save();

    return response()->json([
        'success' => true,
        'message' => 'Avatar deleted successfully'
    ]);
}
}