<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Role;

class AdminRoleController extends Controller
{

    public function getRoles()
    {
        $roles = Role::with('permissionsList')->get();

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }
    public function getRole($id)
    {
        $role = Role::with('permissionsList')->find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $role
        ]);
    }


    // ----------------------------
    // Create Role
    // POST /admin/roles
    // ----------------------------
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id'
        ]);

        $role = Role::create([
            'name' => $request->name
        ]);

        // ✅ Attach permissions via pivot
        if ($request->filled('permission_ids')) {
            $role->permissionsList()->sync($request->permission_ids);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data' => $role->load('permissionsList')
        ], 201);
    }


    // ----------------------------
    // Update Role
    // ----------------------------
    public function updateRole(Request $request, $id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found'
            ], 404);
        }

        // ❌ Prevent editing system roles
        if (in_array($role->name, ['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify system role'
            ], 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255|unique:roles,name,' . $id,
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id'
        ]);

        // ✅ Update name
        if ($request->has('name')) {
            $role->update([
                'name' => $request->name
            ]);
        }

        // ✅ Sync permissions (add/remove automatically)
        if ($request->has('permission_ids')) {
            $role->permissionsList()->sync($request->permission_ids);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data' => $role->load('permissionsList')
        ]);
    }


    // ----------------------------
    // Delete Role
    // ----------------------------
    public function deleteRole($id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found'
            ], 404);
        }

        // ❌ Prevent deleting system roles
        if (in_array($role->name, ['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete system role'
            ], 403);
        }

        // ✅ Use relationship instead of DB query
        if ($role->users()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Role is assigned to users. Cannot delete.'
            ], 400);
        }

        // ✅ Detach permissions before delete (clean pivot)
        $role->permissionsList()->detach();

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully'
        ]);
    }
}