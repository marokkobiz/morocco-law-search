<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ComingSoonController extends Controller
{
    public function __invoke(): View
    {
        return view('coming-soon');
    }
}
