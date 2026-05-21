<?php

namespace App\Http\Controllers\API\Common;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Http\Controllers\API\Common\OtpController;
use App\Models\Otp;
use App\Services\MailService;

class AuthController extends Controller
{

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',  // Removed unique check here
            'phone' => 'nullable|string',  // Removed unique check here
            'password' => 'required|string|min:6|confirmed',
            'type' => 'required|in:email,phone',
        ]);

        // identifier for OTP sending
        $identifier = $request->type === 'email'
            ? $request->email
            : $request->phone;

        if (!$identifier) {
            return response()->json([
                'success' => false,
                'message' => $request->type === 'email' ? 'Email is required' : 'Phone is required'
            ], 422);
        }

        // Check if user already exists in users table
        $userExists = User::where('email', $request->email)
            ->orWhere('phone', $request->phone)
            ->exists();

        if ($userExists) {
            return response()->json([
                'success' => false,
                'message' => 'User with this email or phone already exists'
            ], 400);
        }

        // resend protection (30 seconds cooldown)
        $existing = Otp::where('identifier', $identifier)
            ->where('type', $request->type)
            ->first();

        if ($existing && now()->lt($existing->created_at->addSeconds(30))) {
            return response()->json([
                'success' => false,
                'message' => 'Please wait 30 seconds before requesting another OTP'
            ], 429);
        }

        $otp = rand(100000, 999999);

        // Store or update OTP record
        Otp::updateOrCreate(
            [
                'identifier' => $identifier,
                'type' => $request->type,
            ],
            [
                'otp' => $otp,
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'attempts' => 0,
                'blocked_until' => null,
                'expires_at' => now()->addMinutes(10), // Increased to 10 minutes
            ]
        );

        // SEND OTP
        if ($request->type === 'email') {
            MailService::sendOtpEmail($request->email, $otp);
        }

        if ($request->type === 'phone') {
            // TODO: Integrate SMS service
            // For now, return OTP in response for testing (remove in production)
            // You can use services like Twilio, Vonage, etc.
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully to your ' . $request->type,
            'debug_otp' => app()->environment('local') ? $otp : null, // Only for testing
        ]);
    }

    public function verifyRegister(Request $request)
    {
        $request->validate([
            'identifier' => 'required',
            'type' => 'required|in:email,phone',
            'otp' => 'required|string|size:6',
        ]);

        $record = Otp::where('identifier', $request->identifier)
            ->where('type', $request->type)
            ->first();

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'OTP not found. Please request a new OTP.'
            ], 404);
        }

        // Check if OTP is expired
        if (now()->gt($record->expires_at)) {
            $record->delete();
            return response()->json([
                'success' => false,
                'message' => 'OTP has expired. Please request a new OTP.'
            ], 400);
        }

        // Check blocked status
        if ($record->blocked_until && now()->lt($record->blocked_until)) {
            $remainingMinutes = now()->diffInMinutes($record->blocked_until);
            return response()->json([
                'success' => false,
                'message' => "Too many failed attempts. Please try again after {$remainingMinutes} minutes."
            ], 429);
        }

        // Verify OTP
        if ($record->otp != $request->otp) {
            $record->increment('attempts');
            
            // Block after 5 failed attempts
            if ($record->attempts >= 5) {
                $record->update([
                    'blocked_until' => now()->addMinutes(15)
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Too many failed attempts. Account blocked for 15 minutes.'
                ], 429);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP. ' . (5 - $record->attempts) . ' attempts remaining.'
            ], 400);
        }

        // Final duplicate check before creating user
        $userExists = User::where('email', $record->email)
            ->orWhere('phone', $record->phone)
            ->exists();

        if ($userExists) {
            $record->delete();
            return response()->json([
                'success' => false,
                'message' => 'User already exists with this email or phone number.'
            ], 400);
        }

        // Create user
        $emailVerifiedAt = $record->type === 'email' ? now() : null;
        $phoneVerifiedAt = $record->type === 'phone' ? now() : null;

        $user = User::create([
            'name' => $record->name,
            'email' => $record->email,
            'phone' => $record->phone,
            'password' => $record->password, // Already hashed
            'role_id' => 2,
            'status' => true,
            'email_verified_at' => $emailVerifiedAt,
            'phone_verified_at' => $phoneVerifiedAt,
        ]);

        // Clean up OTP record
        $record->delete();

        // Create login token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registration successful!',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->formatUser($user),
        ]);
    }

    // Resend OTP endpoint
    public function resendOtp(Request $request)
    {
        $request->validate([
            'identifier' => 'required',
            'type' => 'required|in:email,phone',
        ]);

        $record = Otp::where('identifier', $request->identifier)
            ->where('type', $request->type)
            ->first();

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'No pending registration found.'
            ], 404);
        }

        // Check cooldown (30 seconds)
        if (now()->lt($record->created_at->addSeconds(30))) {
            $waitTime = 30 - now()->diffInSeconds($record->created_at);
            return response()->json([
                'success' => false,
                'message' => "Please wait {$waitTime} seconds before requesting another OTP."
            ], 429);
        }

        // Generate new OTP
        $newOtp = rand(100000, 999999);
        
        $record->update([
            'otp' => $newOtp,
            'attempts' => 0,
            'blocked_until' => null,
            'expires_at' => now()->addMinutes(10),
        ]);

        // Resend OTP
        if ($request->type === 'email') {
            MailService::sendOtpEmail($record->email, $newOtp);
        }

        return response()->json([
            'success' => true,
            'message' => 'New OTP sent successfully.',
            'debug_otp' => app()->environment('local') ? $newOtp : null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Login
    |--------------------------------------------------------------------------
    */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::with('role.permissions')
            ->where('email', $request->email)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if (!$user->status) {
            return response()->json([
                'success' => false,
                'message' => 'Account disabled.',
            ], 403);
        }

        $user->last_login_at = now();
        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'user' => $this->formatUser($user),
            'role' => $user->role->name ?? null,
            'permissions' => $this->formatPermissions($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */
    public function logout(Request $request)
    {
        if ($request->user()?->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Refresh Token
    |--------------------------------------------------------------------------
    */
    public function refreshToken(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->currentAccessToken()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $currentToken = $user->currentAccessToken();

        if ($currentToken->created_at->lt(now()->subDays(7))) {
            $currentToken->delete();
            return response()->json([
                'success' => false,
                'message' => 'Token expired.',
            ], 401);
        }

        $currentToken->delete();
        $newToken = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed.',
            'access_token' => $newToken,
            'token_type' => 'Bearer',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Current User
    |--------------------------------------------------------------------------
    */
    public function me(Request $request)
    {
        $user = $request->user()->load('role.permissions');

        return response()->json([
            'success' => true,
            'user' => $this->formatUser($user),
            'role' => $user->role->name ?? null,
            'permissions' => $this->formatPermissions($user),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Forgot Password
    |--------------------------------------------------------------------------
    */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $token = app('auth.password.broker')->createToken($user);

        return response()->json([
            'success' => true,
            'message' => 'Reset token generated.',
            'token' => $token,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Reset Password
    |--------------------------------------------------------------------------
    */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:6|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->save();
                $user->tokens()->delete(); // logout all devices after reset
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => 'Password reset successful.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Password reset failed.',
        ], 422);
    }

    /*
    |--------------------------------------------------------------------------
    | Change Password
    |--------------------------------------------------------------------------
    */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        $user->tokens()->delete(); // force logout all devices

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully. Please login again.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Google Login
    |--------------------------------------------------------------------------
    */
    public function googleLogin(Request $request)
    {
        $request->validate([
            'token' => 'required',
        ]);

        $googleUser = Http::get(
            'https://oauth2.googleapis.com/tokeninfo',
            ['id_token' => $request->token]
        )->json();

        if (!isset($googleUser['email'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Google token.',
            ], 401);
        }

        $user = User::where('email', $googleUser['email'])->first();

        if (!$user) {
            $user = User::create([
                'name' => $googleUser['name'] ?? 'Google User',
                'email' => $googleUser['email'],
                'password' => Hash::make(Str::random(16)),
                'role_id' => 2,
                'status' => 1,
                'email_verified_at' => now(), // Google emails are verified
            ]);
        }

        if (!$user->status) {
            return response()->json([
                'success' => false,
                'message' => 'Account disabled.',
            ], 403);
        }

        $user->load('role.permissions');
        $user->last_login_at = now();
        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'user' => $this->formatUser($user),
            'role' => $user->role->name ?? null,
            'permissions' => $this->formatPermissions($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */
    private function formatUser($user)
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
        ];
    }

    private function formatPermissions($user)
    {
        if (!$user->role || !$user->role->permissions) {
            return [];
        }

        return $user->role->permissions->map(function ($permission) {
            return [
                'module' => $permission->module,
                'action' => $permission->action,
            ];
        })->values();
    }
}