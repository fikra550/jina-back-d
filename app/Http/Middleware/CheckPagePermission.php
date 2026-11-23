<?php
// app/Http/Middleware/CheckPagePermission.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPagePermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$pages
     * @return mixed
     */
    // app/Http/Middleware/CheckPagePermission.php

public function handle(Request $request, Closure $next, ...$pages) {
    $user = $request->user();

    // Admin or superadmin bypasses page restrictions
    if ($user && in_array($user->role, ['admin', 'superadmin'])) {
        return $next($request);
    }

    // Employee: require valid permission for at least one of the requested pages
    foreach ($pages as $page) {
        if ($user && $user->hasValidPermission($page)) {
            return $next($request);
        }
    }

    return response()->json(['message' => 'Forbidden – you do not have access to this page.'], 403);
}
}