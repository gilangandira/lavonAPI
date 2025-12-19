<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (! $request->user()) {
             return response()->json(['message' => 'Unauthorized'], 401);
        }

        $userRole = $request->user()->role; // Assuming 'role' column exists on User model

        // Check if user's role is in the allowed list
        if (! in_array($userRole, $roles)) {
             return response()->json(['message' => 'Forbidden: You do not have permission.'], 403);
        }

        return $next($request);
    }
}
