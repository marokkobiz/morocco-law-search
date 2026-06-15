<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Ai\LegalRagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(
        private readonly LegalRagService $rag,
    ) {}

    public function ask(Request $request): JsonResponse
    {
        $request->validate([
            'question' => 'required|string|max:1000',
        ]);

        $result = $this->rag->ask($request->input('question'));

        return response()->json($result);
    }
}
