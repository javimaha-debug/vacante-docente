<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OposicionTema;
use App\Models\UserDocument;
use App\Services\FlashcardService;
use App\Services\ScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlashcardController extends Controller
{
    public function fromTema(Request $request, FlashcardService $flashcards): JsonResponse
    {
        $data = $request->validate([
            'tema_id' => ['required', 'integer'],
            'count' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $tema = OposicionTema::where('id', $data['tema_id'])->where('user_id', $request->user()->id)->firstOrFail();

        return response()->json([
            'data' => $flashcards->generateFromTema($request->user()->id, $tema, $data['count'] ?? 10),
        ]);
    }

    public function fromDocument(Request $request, FlashcardService $flashcards): JsonResponse
    {
        $data = $request->validate([
            'document_id' => ['required', 'integer'],
            'count' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $doc = UserDocument::where('id', $data['document_id'])->where('user_id', $request->user()->id)->firstOrFail();

        return response()->json([
            'data' => $flashcards->generateFromDocument($request->user()->id, $doc, $data['count'] ?? 10),
        ]);
    }

    public function result(Request $request, ScoringService $scoring): JsonResponse
    {
        $data = $request->validate([
            'tema_id' => ['required', 'integer'],
            'correct' => ['required', 'integer', 'min:0'],
            'total' => ['required', 'integer', 'min:1'],
        ]);

        $tema = OposicionTema::where('id', $data['tema_id'])->where('user_id', $request->user()->id)->firstOrFail();
        $updated = $scoring->updateTemaScore($tema, 'flashcards', $data);

        return response()->json($scoring->present($updated));
    }
}
