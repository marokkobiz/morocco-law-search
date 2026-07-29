<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (auth()->check() && ! auth()->user()->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return view('home');
    }
}
