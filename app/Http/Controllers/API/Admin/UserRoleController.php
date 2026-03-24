<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class UserRoleController extends Controller
{
    /**
     * 🔥 Assign roles to user
     */
    public function assignRoles(Request $request, $userId)
    {
        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,id'
        ]);

        $user = User::findOrFail($userId);

        // ✅ Sync roles (add/remove automatically)
        $user->roles()->sync($request->roles);

        return response()->json([
            'success' => true,
            'message' => 'Roles assigned successfully'
        ]);
    }

    /**
     * 🔥 Get user roles (for UI pre-fill)
     */
    public function getUserRoles($userId)
    {
        $user = User::with('roles')->findOrFail($userId);

        return response()->json([
            'success' => true,
            'data' => $user->roles
        ]);
    }
}