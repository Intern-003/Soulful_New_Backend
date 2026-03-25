<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @param  string $roles  Pipe-separated role names e.g. "admin|vendor"
     */
    public function handle(Request $request, Closure $next, $roles)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $roles = explode('|', $roles);
//dd($roles);
//dd($user->role);
//dd($user->role->name);
        if (!$user->role || !in_array($user->role->name, $roles)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        return $next($request);
    }
}