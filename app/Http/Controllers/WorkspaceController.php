<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class WorkspaceController
{
    public function index(): View
    {
        return view('workspace');
    }
}
