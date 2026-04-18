<?php

namespace App\Http\Controllers\API\Common;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;


class AuthController extends Controller
{
    // ----------------------------
    // Register
    // ----------------------------
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'phone' => 'nullable|string',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'role_id' => 2 // 🔥 no role from user side
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    // ----------------------------
    // Login
    // ----------------------------
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if (!$user->status) {
            return response()->json(['message' => 'Account disabled'], 403);
        }

        // ✅ remove old tokens (BEST PRACTICE)
        $user->tokens()->delete();

        $user->last_login_at = now();
        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;
        // Load role + permissions
        $user->load('role.permissions');

        // Transform permissions
        $permissions = $user->role
            ? $user->role->permissions->map(function ($permission) {
                return [
                    'module' => $permission->module,
                    'action' => $permission->action,
                ];
            })
            : collect();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
            'role' => $user->role->name ?? null,
            'permissions' => $permissions,
            'access_token' => $token,
            'token_type' => 'Bearer'
        ]);

    }

    // ----------------------------
    // Logout
    // ----------------------------
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }

    // ----------------------------
    // Refresh Token
    // ----------------------------
    public function refreshToken(Request $request)
    {
        $user = $request->user();

        // $currentToken = $request->user()->currentAccessToken();
        // if ($currentToken()->created_at->lt(now()->subDays(7))) {
        //     return response()->json(['message' => 'Token expired'], 401);
        // }

        $currentToken = $request->user()->currentAccessToken();

        if ($currentToken->created_at->lt(now()->subDays(7))) {
            return response()->json(['message' => 'Token expired'], 401);
        }

        // delete ONLY current token
        $currentToken()->delete();

        // create new token
        $newToken = $user->createToken('auth_token')->plainTextToken;


        return response()->json([
            'access_token' => $newToken,
            'token_type' => 'Bearer'
        ]);
    }
    // ----------------------------
    // Me
    // ----------------------------
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    // ----------------------------
    // Forgot Password (TEST MODE)
    // ----------------------------
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $token = app('auth.password.broker')->createToken($user);

        return response()->json([
            'message' => 'Reset token generated',
            'token' => $token
        ]);
    }

    // ----------------------------
    // Reset Password
    // ----------------------------
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:6',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password reset successful'])
            : response()->json(['message' => 'Failed'], 422);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ]);

        $user = $request->user();

        // check current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 400);
        }

        // update password
        $user->password = Hash::make($request->new_password);
        $user->save();

        // 🔥 logout all devices after password change
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password changed successfully. Please login again.'
        ]);
    }


    public function googleLogin(Request $request)
    {
        $request->validate([
            'token' => 'required'
        ]);

        // 🔥 Verify token with Google
        $googleUser = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $request->token
        ])->json();

        if (!isset($googleUser['email'])) {
            return response()->json(['message' => 'Invalid Google token'], 401);
        }

        // 🔍 Find or create user
        $user = User::where('email', $googleUser['email'])->first();

        if (!$user) {
            $user = User::create([
                'name' => $googleUser['name'] ?? 'Google User',
                'email' => $googleUser['email'],
                'password' => Hash::make(Str::random(16)), // dummy password
                'role_id' => 2,
                'status' => 1,
            ]);
        }

        if (!$user->status) {
            return response()->json(['message' => 'Account disabled'], 403);
        }

        // ✅ remove old tokens (same as login)
        $user->tokens()->delete();

        $user->last_login_at = now();
        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        // 🔥 Load role + permissions (same as login)
        $user->load('role.permissions');

        $permissions = $user->role
            ? $user->role->permissions->map(function ($permission) {
                return [
                    'module' => $permission->module,
                    'action' => $permission->action,
                ];
            })
            : collect();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
            'role' => $user->role->name ?? null,
            'permissions' => $permissions,
            'access_token' => $token,
            'token_type' => 'Bearer'
        ]);
    }

}