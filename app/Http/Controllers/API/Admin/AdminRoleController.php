<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Role;

class AdminRoleController extends Controller
{
    // ✅ List all roles
    public function index()
    {
        return response()->json(
            Role::with('permissions')->get()
        );
    }

    // ✅ Get single role
    public function show($id)
    {
        $role = Role::with('permissions')->find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'role' => $role
        ]);
    }

    // ✅ Create role
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id'
        ]);

        $role = Role::create([
            'name' => $request->name
        ]);

        // ✅ Always sync (even empty)
        $role->permissions()->sync($request->permission_ids ?? []);

        return response()->json([
            'success' => true,
            'role' => $role->load('permissions')
        ]);
    }

    // ✅ Update role
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $request->validate([
            'name' => 'required|string|unique:roles,name,' . $role->id,
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id'
        ]);

        $role->update([
            'name' => $request->name
        ]);

        // ✅ Always sync
        $role->permissions()->sync($request->permission_ids ?? []);

        return response()->json([
            'success' => true,
            'role' => $role->load('permissions')
        ]);
    }

    // ✅ Delete role
    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        $role->permissions()->detach();
        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted'
        ]);
    }
}