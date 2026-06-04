<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Law;
use App\Models\LawTranslation;
use App\Services\AiReasoningService;
use App\Services\ChatLawService;
use App\Services\LawSearchService;
use App\Services\TranslationService;
use App\Services\TranslationUnavailableException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LawController extends Controller
{
    public function __construct(
        private readonly LawSearchService $laws,
        private readonly AiReasoningService $aiReasoning,
        private readonly ChatLawService $chatLaws,
        private readonly TranslationService $translations,
    )
    {
    }

    public function overview(): JsonResponse
    {
        return response()->json($this->laws->overview());
    }

    public function suggestions(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');

        return response()->json([
            'query' => $query,
            'suggestions' => $this->laws->suggestions($query),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $payload = $this->laws->search($query, LawSearchService::SEARCH_RESULT_LIMIT);

        return response()->json([
            'query' => $query,
            'count' => count($payload['results']),
            'results' => $payload['results'],
            'hasMore' => $payload['hasMore'],
            'limit' => $payload['limit'],
        ]);
    }

    public function chat(Request $request): JsonResponse
    {
        $question = trim((string) $request->input('message', ''));

        if ($question === '') {
            return response()->json(['message' => 'Chat message is required'], 400);
        }

        $history = collect($request->input('history', []))
            ->filter(fn (mixed $message) => is_array($message))
            ->take(-8)
            ->map(fn (array $message) => [
                'role' => ($message['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user',
                'text' => trim((string) ($message['text'] ?? '')),
            ])
            ->filter(fn (array $message) => $message['text'] !== '')
            ->values()
            ->all();
        $intent = $this->chatLaws->classifyIntent($question, $history);
        $aiPlan = $intent === ChatLawService::INTENT_CASE_ANALYSIS
            ? $this->aiReasoning->createSearchPlan($question, $history)
            : null;
        $context = $this->chatLaws->prepare($question, $history, $aiPlan, $intent);
        $citations = $context['citations'];

        return response()->json([
            'question' => $question,
            'intent' => $context['intent'] ?? $intent,
            'answer' => $context['answer']
                ?? $this->aiReasoning->answer($question, $citations, $context['plan'] ?? ['aiPlan' => $aiPlan])
                ?? $context['fallbackAnswer'],
            'citations' => $citations,
        ]);
    }

    public function translate(Law $law, Request $request): JsonResponse
    {
        $targetLanguage = strtolower(trim((string) $request->query('target', 'en'))) ?: 'en';
        $stored = LawTranslation::query()
            ->where('law_id', $law->id)
            ->where('target_language', $targetLanguage)
            ->first();

        if ($stored) {
            return response()->json($this->translationPayload($law, $stored, true));
        }

        try {
            $translation = $this->translations->translate($law, $targetLanguage);
        } catch (TranslationUnavailableException) {
            return response()->json([
                'message' => 'Inline translation is temporarily unavailable.',
                'fallbackUrl' => $this->translations->buildExternalTranslationUrl($law, $targetLanguage),
            ], 503);
        }

        $stored = LawTranslation::query()->updateOrCreate(
            [
                'law_id' => $law->id,
                'target_language' => $translation['targetLanguage'],
            ],
            [
                'source_language' => $translation['sourceLanguage'],
                'translated_title' => $translation['translatedTitle'],
                'translated_content' => $translation['translatedContent'],
                'provider' => $translation['provider'],
            ]
        );

        return response()->json($this->translationPayload($law, $stored, false));
    }

    private function translationPayload(Law $law, LawTranslation $translation, bool $cached): array
    {
        return [
            'id' => $law->id,
            'articleNumber' => $law->article_number,
            'documentTitle' => $law->document_title,
            'sourceUrl' => $law->source_url,
            'sourceLanguage' => $translation->source_language,
            'targetLanguage' => $translation->target_language,
            'translatedTitle' => $translation->translated_title,
            'translatedContent' => $translation->translated_content,
            'provider' => $translation->provider,
            'cached' => $cached,
        ];
    }
}
