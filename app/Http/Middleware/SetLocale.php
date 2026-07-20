<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    private const ALLOWED = ['en', 'fr', 'ar'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = null;

        if ($request->has('lang')) {
            $candidate = $request->input('lang');
            if (in_array($candidate, self::ALLOWED, true)) {
                $locale = $candidate;
            }
        }

        if (!$locale) {
          $locale = session('locale', 'ar');
        }

        if (!in_array($locale, self::ALLOWED, true)) {
            $locale = 'en';
        }

        session(['locale' => $locale]);
        app()->setLocale($locale);

        return $next($request);
    }
}
