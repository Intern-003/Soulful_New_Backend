<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class AdminRoleController extends Controller
{

    // POST /admin/roles
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'nullable|array'
        ]);

        $role = Role::create([
            'name' => $request->name,
            'permissions' => $request->permissions
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data' => $role
        ],201);
    }


    public function deleteRole($id)
{
    $role = Role::find($id);

    if (!$role) {
        return response()->json([
            'success' => false,
            'message' => 'Role not found'
        ], 404);
    }

    // ❌ Prevent deleting system roles (optional but recommended)
    if (in_array($role->name, ['admin', 'super_admin'])) {
        return response()->json([
            'success' => false,
            'message' => 'Cannot delete system role'
        ], 403);
    }

    // ❌ Check if role is assigned to users
    if (DB::table('users')->where('role_id', $id)->exists()) {
        return response()->json([
            'success' => false,
            'message' => 'Role is assigned to users. Cannot delete.'
        ], 400);
    }

    $role->delete();

    return response()->json([
        'success' => true,
        'message' => 'Role deleted successfully'
    ]);
}

public function updateRole(Request $request, $id)
{
    $role = Role::find($id);

    if (!$role) {
        return response()->json([
            'success' => false,
            'message' => 'Role not found'
        ], 404);
    }

    // ❌ Prevent editing system roles (optional)
    if (in_array($role->name, ['admin', 'super_admin'])) {
        return response()->json([
            'success' => false,
            'message' => 'Cannot modify system role'
        ], 403);
    }

    $request->validate([
        'name' => 'sometimes|string|max:255|unique:roles,name,' . $id,
        'permissions' => 'nullable|array'
    ]);

    $data = [];

    if ($request->has('name')) {
        $data['name'] = $request->name;
    }

    // ✅ If using JSON permissions
    if ($request->has('permissions')) {
        $data['permissions'] = $request->permissions;
    }

    $role->update($data);

    return response()->json([
        'success' => true,
        'message' => 'Role updated successfully',
        'data' => $role
    ]);
}

}