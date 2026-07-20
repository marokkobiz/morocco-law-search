<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class LegalAidController extends Controller
{
    public function index()
    {
        return view('legal-aid');
    }
}
