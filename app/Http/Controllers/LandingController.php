<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class LandingController
{
    public function index(): View
    {
        return view('home');
    }
}
