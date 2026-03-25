<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Permission;

class AdminPermissionController extends Controller
{
    // List all permissions
    public function index()
    {
        return response()->json(Permission::all());
    }

    // --------------------------
    // Get Permission by ID
    // --------------------------
    public function show($id)
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
            'permission' => $permission
        ]);
    }
    // Create permission
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:permissions,name',
            'module' => 'required|string',
            'action' => 'required|string',
        ]);

        $permission = Permission::create($request->all());

        return response()->json([
            'success' => true,
            'permission' => $permission
        ]);
    }

    // Update permission
    public function update(Request $request, $id)
    {
        $permission = Permission::findOrFail($id);

        $request->validate([
            'name' => 'required|string|unique:permissions,name,' . $permission->id,
            'module' => 'required|string',
            'action' => 'required|string',
        ]);

        $permission->update($request->all());

        return response()->json([
            'success' => true,
            'permission' => $permission
        ]);
    }

    // Delete permission
    public function destroy($id)
    {
        $permission = Permission::findOrFail($id);
        $permission->roles()->detach(); // remove from role_permissions pivot
        $permission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted'
        ]);
    }

}