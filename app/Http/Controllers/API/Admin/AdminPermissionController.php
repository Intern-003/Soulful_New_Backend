<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Permission;

class AdminPermissionController extends Controller
{

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
            'name' => 'required|string|max:255|unique:permissions,name'
        ]);

        $permission = Permission::create([
            'name' => $request->name
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permission created successfully',
            'data' => $permission
        ], 201);
    }


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
        if ($permission->roles()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Permission is assigned to roles. Cannot delete.'
            ], 400);
        }

        $permission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successfully'
        ]);
    }
}