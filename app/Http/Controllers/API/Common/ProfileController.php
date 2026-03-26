<?php

namespace App\Http\Controllers\API\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserProfile;

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
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = $request->user();

        $profile = UserProfile::firstOrCreate([
            'user_id' => $user->id
        ]);

        $file = $request->file('avatar');

        $filename = time() . '_' . $user->id . '.' . $file->getClientOriginalExtension();

        $destination = public_path('uploads/avatars');

        if (!file_exists($destination)) {
            mkdir($destination, 0755, true);
        }

        // delete old avatar
        if ($profile->avatar && file_exists(public_path($profile->avatar))) {
            unlink(public_path($profile->avatar));
        }

        $file->move($destination, $filename);

        $profile->avatar = 'uploads/avatars/' . $filename;
        $profile->save();

        return response()->json([
            'message' => 'Avatar uploaded successfully',
            'avatar_url' => url($profile->avatar)
        ]);
    }

    // ----------------------------
    // Delete Avatar
    // ----------------------------
    public function deleteAvatar(Request $request)
    {
        $user = $request->user();

        $profile = $user->profile;

        if (!$profile || !$profile->avatar) {
            return response()->json([
                'message' => 'No avatar found'
            ], 404);
        }

        if (file_exists(public_path($profile->avatar))) {
            unlink(public_path($profile->avatar));
        }

        $profile->avatar = null;
        $profile->save();

        return response()->json([
            'message' => 'Avatar deleted successfully'
        ]);
    }
}