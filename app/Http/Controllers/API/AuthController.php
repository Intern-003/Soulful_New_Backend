<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    // ----------------------------
    // Register new user/vendor
    // ----------------------------
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'phone' => 'nullable|string',
            'role_id' => 'required|exists:roles,id',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'role_id' => $request->role_id,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    // ----------------------------
    // Login user
    // ----------------------------
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    // ----------------------------
    // Logout
    // ----------------------------
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully.']);
    }

    // ----------------------------
    // Refresh Token
    // ----------------------------
    public function refreshToken(Request $request)
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    // ----------------------------
    // Get Current Authenticated User
    // ----------------------------
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    // ----------------------------
    // Forgot Password (Send Reset Link)
    // ----------------------------
    // public function forgotPassword(Request $request)
    // {
    //     $request->validate(['email' => 'required|email']);

    //     $status = Password::sendResetLink($request->only('email'));

    //     return $status === Password::RESET_LINK_SENT
    //         ? response()->json(['message' => __($status)])
    //         : response()->json(['message' => __($status)], 422);
    // }

    public function forgotPassword(Request $request)
{
    $request->validate(['email' => 'required|email']);
    
    // Find the user
    $user = User::where('email', $request->email)->first();
    
    if (!$user) {
        return response()->json(['message' => 'User not found'], 404);
    }
    
    // Generate a token manually
    $token = app('auth.password.broker')->createToken($user);
    
    // FOR TESTING: Return the token directly
    return response()->json([
        'message' => 'Reset link generated (TESTING MODE)',
        'reset_token' => $token,
        'email' => $user->email,
        'reset_url' => 'http://your-frontend.com/reset-password?token=' . $token . '&email=' . $user->email
    ]);
}

    // ----------------------------
    // Reset Password
    // ----------------------------
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|confirmed|min:6',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 422);
    }

    // ----------------------------
    // Verify Email
    // ----------------------------
    public function verifyEmail(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified']);
        }

        $request->user()->markEmailAsVerified();

        return response()->json(['message' => 'Email verified successfully']);
    }

    // ----------------------------
    // Get User Profile
    // ----------------------------
    public function profile(Request $request)
    {
        return response()->json($request->user());
    }

    // ----------------------------
    // Update Profile
    // ----------------------------
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|nullable',
        ]);

        $user->update($request->only('name', 'phone'));

        return response()->json($user);
    }

    // ----------------------------
    // Change Password
    // ----------------------------
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|confirmed|min:6',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password does not match'], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Password updated successfully']);
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
        'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
    ]);

    $user = $request->user();

    $file = $request->file('avatar');

    // original filename
    $originalName = $file->getClientOriginalName();

    // get filename without extension
    $name = pathinfo($originalName, PATHINFO_FILENAME);

    // extension
    $extension = $file->getClientOriginalExtension();

    // new filename
    $filename = $name . '_' . $user->id . '.' . $extension;

    // upload path
    $destination = public_path('uploads/avatars');

    // create folder if not exists
    if (!file_exists($destination)) {
        mkdir($destination, 0755, true);
    }

    // delete old avatar
    if ($user->avatar && file_exists(public_path($user->avatar))) {
        unlink(public_path($user->avatar));
    }

    // move file
    $file->move($destination, $filename);

    // save path in DB
    $user->avatar = 'uploads/avatars/' . $filename;
    $user->save();

    return response()->json([
        'message' => 'Avatar uploaded successfully',
        'avatar' => $user->avatar,
        'url' => url($user->avatar)
    ]);
}
}