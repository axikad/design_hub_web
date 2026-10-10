<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission, string $action = 'read'): Response
    {
        if (! $request->user()?->hasPermission($permission, $action)) {
            abort(403);
        }

        return $next($request);
    }
}
