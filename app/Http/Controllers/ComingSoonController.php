<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ComingSoonController
{
    public function index (): View
    {
        return view('coming-soon');
    }
}
