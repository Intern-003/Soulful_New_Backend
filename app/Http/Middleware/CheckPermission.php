<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $module, string $action): Response
    {
        $user = $request->user();

        // ❌ Not logged in
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        // ✅ Super Admin bypass
        if ($user->role === 'super_admin') {
            return $next($request);
        }

        // ❌ Permission check
        if (!$user->hasPermission($module, $action)) {
            return response()->json([
                'success' => false,
                'message' => "Permission denied: {$module}.{$action}"
            ], 403);
        }

        return $next($request);
    }
}