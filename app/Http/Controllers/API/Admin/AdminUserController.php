<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminUserController extends Controller
{
    public function assignUserPermissions(Request $request, $userId)
{
    $request->validate([
        'permissions' => 'required|array',
        'permissions.*.permission_id' => 'required|exists:permissions,id',
        'permissions.*.is_allowed' => 'required|boolean'
    ]);

    foreach ($request->permissions as $perm) {
        DB::table('user_permissions')->updateOrInsert(
            [
                'user_id' => $userId,
                'permission_id' => $perm['permission_id']
            ],
            [
                'is_allowed' => $perm['is_allowed']
            ]
        );
    }

    return response()->json([
        'success' => true,
        'message' => 'User permissions updated successfully'
    ]);
}
}
