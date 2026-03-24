<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\Permission;

class AdminRoleController extends Controller
{
    /**
     * ✅ Get all roles
     */
    public function index()
    {
        $roles = Role::all();

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    /**
     * ✅ Create Role + Assign Permissions
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        $role = Role::create([
            'name' => $request->name,
        ]);

        // ✅ Assign permissions if provided
        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data' => $role
        ], 201);
    }

    /**
     * ✅ Update Role Name
     */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        if (in_array($role->name, ['admin', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify system role'
            ], 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255|unique:roles,name,' . $id,
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
}