<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;


class AdminPermissionController extends Controller
{

    // POST /admin/permissions
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
        ],201);
    }


    public function deletePermission($id)
{
    $permission = Permission::find($id);

    if (!$permission) {
        return response()->json([
            'success' => false,
            'message' => 'Permission not found'
        ], 404);
    }

    // ❌ Prevent delete if assigned to roles (pivot table)
    if (DB::table('role_permissions')->where('permission_id', $id)->exists()) {
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

}