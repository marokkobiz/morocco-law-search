<?php

namespace App\Http\Controllers;

use App\Services\CorpusStatusService;
use Illuminate\View\View;

class CorpusStatusController extends Controller
{
    public function show(CorpusStatusService $corpus): View
    {
        return view('corpus-status', [
            'status' => $corpus->status(),
        ]);
    }
}
