<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Permission;

class AdminPermissionController extends Controller
{
    // ✅ List all permissions
    public function index()
    {
        return response()->json(Permission::all());
    }

    // ✅ Get Permission by ID
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

    // ✅ Create permission (AUTO NAME)
    public function store(Request $request)
    {
        $request->validate([
            'module' => 'required|string',
            'action' => 'required|string',
        ]);

        // ✅ Normalize
        $module = strtolower(trim($request->module));
        $action = strtolower(trim($request->action));

        // ✅ Auto generate name
        $name = $module . '.' . $action;

        // ✅ Prevent duplicates
        $exists = Permission::where('module', $module)
            ->where('action', $action)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Permission already exists'
            ], 422);
        }

        $permission = Permission::create([
            'name' => $name,
            'module' => $module,
            'action' => $action,
        ]);

        return response()->json([
            'success' => true,
            'permission' => $permission
        ]);
    }

    // ✅ Update permission (AUTO NAME)
    public function update(Request $request, $id)
    {
        $permission = Permission::findOrFail($id);

        $request->validate([
            'module' => 'required|string',
            'action' => 'required|string',
        ]);

        $module = strtolower(trim($request->module));
        $action = strtolower(trim($request->action));
        $name = $module . '.' . $action;

        // ✅ Prevent duplicate (excluding current)
        $exists = Permission::where('module', $module)
            ->where('action', $action)
            ->where('id', '!=', $permission->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Permission already exists'
            ], 422);
        }

        $permission->update([
            'name' => $name,
            'module' => $module,
            'action' => $action,
        ]);

        return response()->json([
            'success' => true,
            'permission' => $permission
        ]);
    }

    // ✅ Delete permission
    public function destroy($id)
    {
        $permission = Permission::findOrFail($id);

        $permission->roles()->detach();
        $permission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted'
        ]);
    }
}