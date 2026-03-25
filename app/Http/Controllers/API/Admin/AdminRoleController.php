<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\Permission;

class AdminRoleController extends Controller
{
       // List all roles
    public function index()
    {
        $roles = Role::with('permissions')->get();
        return response()->json($roles);
    }

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
    
    // Create a role
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name',
            'permissions' => 'nullable|array'
        ]);

        $role = Role::create(['name' => $request->name]);

        if ($request->permissions) {
            $role->permissions()->sync($request->permissions);
        }

        return response()->json([
            'success' => true,
            'role' => $role->load('permissions')
        ]);
    }

    // Update a role
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $request->validate([
            'name' => 'required|string|unique:roles,name,' . $role->id,
            'permissions' => 'nullable|array'
        ]);

        $role->update(['name' => $request->name]);

        if ($request->permissions) {
            $role->permissions()->sync($request->permissions);
        }

        return response()->json([
            'success' => true,
            'role' => $role->load('permissions')
        ]);
    }

    // Delete a role
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