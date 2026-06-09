<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CorpusStatusService;
use Illuminate\Http\JsonResponse;

class CorpusController extends Controller
{
    public function status(CorpusStatusService $corpus): JsonResponse
    {
        return response()->json($corpus->status());
    }
}
