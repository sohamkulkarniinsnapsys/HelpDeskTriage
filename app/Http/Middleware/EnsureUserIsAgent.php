<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAgent
{
    /**
     * Handle an incoming request.
     *
     * Ensures the authenticated user has the Agent role.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->role->isAgent()) {
            abort(403, 'This action requires agent privileges.');
        }

        return $next($request);
    }
}
