<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;

class AdminUserController extends Controller
{
    /**
     * List all users with roles
     * GET /admin/users-with-roles
     */
    public function index()
    {
        $users = User::with('role')->get();
        
        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Get single user with role
     * GET /admin/users/{id}
     */
    public function show($id)
    {
        $user = User::with('role')->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * ✅ CREATE USER
     * POST /admin/users
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $validated['role_id'],
            'status' => true, // Default status as boolean
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => $user->load('role')
        ], 201);
    }

    /**
     * ✅ UPDATE USER - FIXED VALIDATION
     * PUT /admin/users/{id}
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // ✅ Simplified validation - accept any status format
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'status' => 'nullable|boolean', // ✅ Accept boolean only
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        // Update name if provided
        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }

        // Update email if provided
        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }

        // ✅ Update status - convert to boolean
        if (array_key_exists('status', $validated)) {
            $user->status = (bool) $validated['status'];
        }

        // Update password if provided
        if (isset($validated['password']) && !empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user->load('role')
        ]);
    }

    /**
     * ✅ DELETE USER
     * DELETE /admin/users/{id}
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        
        // Prevent deleting self
        if (auth()->id() == $id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account'
            ], 403);
        }

        // Prevent deleting last admin
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole && $user->role_id == $adminRole->id) {
            $adminCount = User::where('role_id', $adminRole->id)->count();
            if ($adminCount <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete the last admin user'
                ], 403);
            }
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * ✅ ASSIGN ROLE TO USER
     * POST /admin/users/{id}/assign-role
     */
    public function assignRole(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $user->role_id = $request->role_id;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Role assigned successfully',
            'data' => $user->load('role')
        ]);
    }

    /**
     * ✅ BULK UPDATE STATUS
     * POST /admin/users/bulk-status
     */
    public function bulkUpdateStatus(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'status' => 'required|boolean',
        ]);

        User::whereIn('id', $request->user_ids)
            ->update(['status' => (bool) $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Users status updated successfully',
            'updated_count' => count($request->user_ids)
        ]);
    }
}