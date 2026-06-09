<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePaidAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('billing.require_payment')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user && $user->hasBillableAccess()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Active billing is required to use the legal research workspace.',
                'billingUrl' => url('/billing'),
            ], 402);
        }

        return redirect('/billing?lang='.$this->language($request));
    }

    private function language(Request $request): string
    {
        $lang = (string) $request->query('lang', 'en');

        return in_array($lang, ['en', 'fr', 'ar'], true) ? $lang : 'en';
    }
}
