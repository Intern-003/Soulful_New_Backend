<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Role;
<<<<<<< HEAD
use App\Models\Permission;
=======
>>>>>>> a0a39bc8f6dacae767bf89e4f7e4aaaf6e9fa8f5

class AdminRoleController extends Controller
{
    /**
     * ✅ Get all roles
     */
    public function index()
    {
        $roles = Role::all();

<<<<<<< HEAD
=======
    public function getRoles()
    {
        $roles = Role::with('permissionsList')->get();

>>>>>>> a0a39bc8f6dacae767bf89e4f7e4aaaf6e9fa8f5
        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }
<<<<<<< HEAD

    /**
     * ✅ Create Role + Assign Permissions
     */
=======
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
>>>>>>> a0a39bc8f6dacae767bf89e4f7e4aaaf6e9fa8f5
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
<<<<<<< HEAD
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        $role = Role::create([
            'name' => $request->name,
        ]);

        // ✅ Assign permissions if provided
        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
=======
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id'
        ]);

        $role = Role::create([
            'name' => $request->name
        ]);

        // ✅ Attach permissions via pivot
        if ($request->filled('permission_ids')) {
            $role->permissionsList()->sync($request->permission_ids);
>>>>>>> a0a39bc8f6dacae767bf89e4f7e4aaaf6e9fa8f5
        }

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
<<<<<<< HEAD
            'data' => $role
=======
            'data' => $role->load('permissionsList')
>>>>>>> a0a39bc8f6dacae767bf89e4f7e4aaaf6e9fa8f5
        ], 201);
    }

    /**
     * ✅ Update Role Name
     */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

<<<<<<< HEAD
=======
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
>>>>>>> a0a39bc8f6dacae767bf89e4f7e4aaaf6e9fa8f5
        if (in_array($role->name, ['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify system role'
            ], 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255|unique:roles,name,' . $id,
<<<<<<< HEAD
        ]);

        $role->update($request->only('name'));

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data' => $role
        ]);
    }

    /**
     * ✅ Delete Role
     */
    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        if (in_array($role->name, ['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete system role'
            ], 403);
        }

        if ($role->users()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Role is assigned to users'
            ], 400);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully'
        ]);
    }

    /**
     * 🔥 Assign / Update Role Permissions (Checkbox Save)
     */
    public function assignPermissions(Request $request, $roleId)
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        $role = Role::findOrFail($roleId);

        $role->permissions()->sync($request->permissions);

        return response()->json([
            'success' => true,
            'message' => 'Permissions assigned successfully'
        ]);
    }

    /**
     * 🔥 Get Role Permissions (Checkbox Pre-fill)
     */
    public function getRolePermissions($roleId)
    {
        $role = Role::with('permissions')->findOrFail($roleId);

        $allPermissions = Permission::all()->groupBy('module');

        $rolePermissionIds = $role->permissions->pluck('id')->toArray();

        $formatted = [];

        foreach ($allPermissions as $module => $perms) {
            $formatted[$module] = $perms->map(function ($perm) use ($rolePermissionIds) {
                return [
                    'id' => $perm->id,
                    'name' => $perm->name,
                    'module' => $perm->module,
                    'action' => $perm->action,
                    'checked' => in_array($perm->id, $rolePermissionIds),
                ];
            });
        }

        return response()->json([
            'success' => true,
            'data' => $formatted
        ]);
    }
=======
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
>>>>>>> a0a39bc8f6dacae767bf89e4f7e4aaaf6e9fa8f5
}