<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class AdminUserController extends Controller
{
 // Assign role to user
    public function assignRole(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $user->role_id = $request->role_id;
        $user->save();

        return response()->json([
            'success' => true,
            'user' => $user->load('role')
        ]);
    }

    // List all users with roles
    public function index()
    {
        return response()->json(User::with('role')->get());
    }

}
