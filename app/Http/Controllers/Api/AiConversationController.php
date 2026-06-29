<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Services\AiAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = AiConversation::where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'mode', 'context_type', 'especialidad_code', 'updated_at']);

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'in:chat,flashcards,simulacro,simulador_oral'],
            'context_type' => ['required', 'in:free,temario,document'],
            'especialidad_code' => ['nullable', 'string', 'max:50'],
            'tema_numero' => ['nullable', 'integer', 'min:1'],
            'title' => ['nullable', 'string', 'max:255'],
            'document_ids' => ['sometimes', 'array'],
        ]);

        $conversation = AiConversation::create([
            'user_id' => $request->user()->id,
            'mode' => $data['mode'],
            'context_type' => $data['context_type'],
            'especialidad_code' => $data['especialidad_code'] ?? null,
            'tema_numero' => $data['tema_numero'] ?? null,
            'title' => $data['title'] ?? null,
        ]);

        return response()->json($conversation, 201);
    }

    public function show(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->assertOwns($request, $conversation);

        return response()->json([
            'conversation' => $conversation,
            'messages' => $conversation->messages()->orderBy('id')->get(),
        ]);
    }

    public function message(Request $request, AiConversation $conversation, AiAssistantService $assistant): JsonResponse
    {
        $this->assertOwns($request, $conversation);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:8000'],
            'document_ids' => ['sometimes', 'array'],
            'document_ids.*' => ['integer'],
        ]);

        if (! $assistant->withinDailyLimit($request->user()->id)) {
            return response()->json([
                'message' => 'Has alcanzado el límite diario de mensajes. Inténtalo de nuevo mañana.',
            ], 429);
        }

        $result = $assistant->chat($request->user(), $conversation, $data['message'], [
            'document_ids' => $data['document_ids'] ?? null,
        ]);

        return response()->json([
            'message' => $result['message'],
            'citations' => $result['citations'],
        ]);
    }

    public function destroy(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->assertOwns($request, $conversation);
        $conversation->delete();

        return response()->json(['deleted' => true]);
    }

    private function assertOwns(Request $request, AiConversation $conversation): void
    {
        abort_unless($conversation->user_id === $request->user()->id, 403);
    }
}
