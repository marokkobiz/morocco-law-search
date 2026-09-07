<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdvisor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== 'advisor') {
            abort(403, 'Unauthorized access.');
        }

        if ($user->access_status === 'suspended') {
            abort(403, 'Your account has been suspended.');
        }

        app()->setLocale('en');

        return $next($request);
    }
}
