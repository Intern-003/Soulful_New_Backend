<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user('sanctum');

        // ❌ Not logged in
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        // Load role + permissions
        $user->loadMissing('role.permissions');

        // ✅ Super Admin bypass
        if ($user->role && $user->role->name === 'admin') {
            return $next($request);
        }

        // ❌ Permission check
        if (!$user->hasPermission($permission)) {
            return response()->json([
                'success' => false,
                'message' => "Permission denied: {$permission}"
            ], 403);
        }

        return $next($request);
    }
}