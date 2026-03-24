<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Permission;

class AdminPermissionController extends Controller
{
    /**
     * ✅ Get all permissions (grouped for checkbox UI)
     */
    public function index()
    {
        $permissions = Permission::all()
            ->groupBy('module')
            ->map(function ($group) {
                return $group->map(function ($perm) {
                    return [
                        'id' => $perm->id,
                        'name' => $perm->name,
                        'module' => $perm->module,
                        'action' => $perm->action,
                    ];
                });
            });

        return response()->json([
            'success' => true,
            'data' => $permissions
        ]);
    }

    /**
     * ✅ Create Permission
     */
public function getPermissions()
{
    $permissions = Permission::all();

    return response()->json([
        'success' => true,
        'data' => $permissions
    ]);
}
public function getPermission($id)
{
    $permission = Permission::find($id);

    if (!$permission) {
        return response()->json([
            'success' => false,
            'message' => 'Permission not found'
        ], 404);
    }

    return response()->json([
        'success' => true,
        'data' => $permission
    ]);
}

    // ----------------------------
    // Create Permission
    // POST /admin/permissions
    // ----------------------------
    public function store(Request $request)
    {
        $request->validate([
            'module' => 'required|string|max:100',
            'action' => 'required|string|max:100',
        ]);

        $name = strtolower($request->module) . '.' . strtolower($request->action);

        // ✅ Prevent duplicate (extra safety)
        if (Permission::where('name', $name)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Permission already exists'
            ], 409);
        }

        $permission = Permission::create([
            'name' => $name,
            'module' => strtolower($request->module),
            'action' => strtolower($request->action),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permission created successfully',
            'data' => $permission
        ], 201);
    }

    /**
     * ✅ Update Permission
     */
    public function update(Request $request, $id)
    {
        $permission = Permission::findOrFail($id);

        $request->validate([
            'module' => 'required|string|max:100',
            'action' => 'required|string|max:100',
        ]);

        $name = strtolower($request->module) . '.' . strtolower($request->action);

        // ✅ Prevent duplicate on update
        if (Permission::where('name', $name)->where('id', '!=', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Permission already exists'
            ], 409);
        }

        $permission->update([
            'module' => strtolower($request->module),
            'action' => strtolower($request->action),
            'name' => $name,
    // ----------------------------
    // Update Permission
    // ----------------------------
    public function updatePermission(Request $request, $id)
    {
        $permission = Permission::find($id);

        if (!$permission) {
            return response()->json([
                'success' => false,
                'message' => 'Permission not found'
            ], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name,' . $id
        ]);

        $permission->update([
            'name' => $request->name
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permission updated successfully',
            'data' => $permission
        ]);
    }

<<<<<<< HEAD
    /**
     * ✅ Delete Permission
     */
    public function destroy($id)
    {
        $permission = Permission::findOrFail($id);

        // ❌ Prevent delete if used in roles
=======

    // ----------------------------
    // Delete Permission
    // ----------------------------
    public function deletePermission($id)
    {
        $permission = Permission::find($id);

        if (!$permission) {
            return response()->json([
                'success' => false,
                'message' => 'Permission not found'
            ], 404);
        }

        // ✅ Use relationship instead of DB query
>>>>>>> a0a39bc8f6dacae767bf89e4f7e4aaaf6e9fa8f5
        if ($permission->roles()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Permission is assigned to roles. Cannot delete.'
            ], 400);
        }

<<<<<<< HEAD
        // ❌ Prevent delete if used in user overrides
        if ($permission->users()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Permission is assigned to users. Cannot delete.'
            ], 400);
        }

=======
>>>>>>> a0a39bc8f6dacae767bf89e4f7e4aaaf6e9fa8f5
        $permission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successfully'
        ]);
    }
<<<<<<< HEAD
    // Assign permissions to role
public function assignPermissionsToRole(Request $request, $roleId)
{
    $request->validate([
        'permissions' => 'required|array',
        'permissions.*' => 'exists:permissions,id'
    ]);

    $role = \App\Models\Role::findOrFail($roleId);

    $role->permissions()->sync($request->permissions);

    return response()->json([
        'success' => true,
        'message' => 'Permissions assigned to role successfully'
    ]);
}



public function getRolePermissions($roleId)
{
    $role = \App\Models\Role::with('permissions')->findOrFail($roleId);

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

public function getGroupedPermissions()
{
    $permissions = \App\Models\Permission::all()
        ->groupBy('module')
        ->map(function ($items, $module) {
            return [
                'module' => $module,
                'actions' => $items->map(function ($perm) {
                    return [
                        'id' => $perm->id,
                        'action' => $perm->action
                    ];
                })->values()
            ];
        })->values();

    return response()->json([
        'success' => true,
        'data' => $permissions
    ]);
}

public function getUserPermissions($userId)
{
    $user = \App\Models\User::with('permissions')->findOrFail($userId);

    return response()->json([
        'success' => true,
        'data' => $user->permissions->map(function ($perm) {
            return [
                'permission_id' => $perm->id,
                'module' => $perm->module,
                'action' => $perm->action,
                'is_allowed' => $perm->pivot->is_allowed
            ];
        })
    ]);
}


=======
>>>>>>> a0a39bc8f6dacae767bf89e4f7e4aaaf6e9fa8f5
}