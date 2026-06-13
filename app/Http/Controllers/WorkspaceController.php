<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function __invoke(): View
    {
        return view('workspace');
    }
}
