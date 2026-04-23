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

class AuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register
    |--------------------------------------------------------------------------
    */
    public function register(Request $request)
    {
        $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|email|unique:users,email',
            'password'              => 'required|string|min:6|confirmed',
            'phone'                 => 'nullable|string|max:20',
        ]);

        $user = User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'phone'     => $request->phone,
            'role_id'   => 2,
            'status'    => 1,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success'       => true,
            'message'       => 'Registration successful.',
            'user'          => $this->formatUser($user),
            'role'          => $user->role->name ?? null,
            'permissions'   => [],
            'access_token'  => $token,
            'token_type'    => 'Bearer',
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Login
    |--------------------------------------------------------------------------
    */
    public function login(Request $request)
    {
        $request->validate([
            'email'     => 'required|email',
            'password'  => 'required|string',
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

        /*
        IMPORTANT FIX:
        Removed ->tokens()->delete()
        It was invalidating tokens in other tabs/sessions
        causing Unauthenticated issue.
        */

        $user->last_login_at = now();
        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success'       => true,
            'message'       => 'Login successful.',
            'user'          => $this->formatUser($user),
            'role'          => $user->role->name ?? null,
            'permissions'   => $this->formatPermissions($user),
            'access_token'  => $token,
            'token_type'    => 'Bearer',
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
            'success'       => true,
            'message'       => 'Token refreshed.',
            'access_token'  => $newToken,
            'token_type'    => 'Bearer',
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
            'success'       => true,
            'user'          => $this->formatUser($user),
            'role'          => $user->role->name ?? null,
            'permissions'   => $this->formatPermissions($user),
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
            'token'   => $token,
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
            'token'                  => 'required',
            'email'                  => 'required|email',
            'password'               => 'required|min:6|confirmed',
        ]);

        $status = Password::reset(
            $request->only(
                'email',
                'password',
                'password_confirmation',
                'token'
            ),
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->save();

                // logout all devices after reset
                $user->tokens()->delete();
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
            'current_password'           => 'required',
            'new_password'               => 'required|min:6|confirmed',
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

        // force logout all devices
        $user->tokens()->delete();

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
                'name'      => $googleUser['name'] ?? 'Google User',
                'email'     => $googleUser['email'],
                'password'  => Hash::make(Str::random(16)),
                'role_id'   => 2,
                'status'    => 1,
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
            'success'       => true,
            'message'       => 'Login successful.',
            'user'          => $this->formatUser($user),
            'role'          => $user->role->name ?? null,
            'permissions'   => $this->formatPermissions($user),
            'access_token'  => $token,
            'token_type'    => 'Bearer',
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
            'id'        => $user->id,
            'name'      => $user->name,
            'email'     => $user->email,
            'phone'     => $user->phone,
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