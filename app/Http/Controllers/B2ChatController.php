<?php

namespace App\Http\Controllers;

use App\Models\B2ChatHistory;
use App\Models\B2ExerciseHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class B2ChatController extends Controller
{
    public function sendMessage(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'exercise_history_id' => 'nullable|integer|exists:b2_exercise_history,id',
            'context_type' => 'nullable|string|in:why_failed,general,help,writing_feedback,speaking_feedback',
        ]);

        $user = Auth::user();
        $historyId = $request->exercise_history_id;

        // Build context
        $systemPrompt = $this->buildSystemPrompt($historyId);
        $conversationHistory = B2ChatHistory::getConversationContext($user->id, $historyId, 8);

        // Save user message
        B2ChatHistory::create([
            'user_id' => $user->id,
            'exercise_history_id' => $historyId,
            'role' => 'user',
            'content' => $request->message,
            'context_type' => $request->context_type ?? 'general',
        ]);

        // Call Claude API
        $response = $this->callClaudeApi($systemPrompt, $conversationHistory, $request->message);

        // Save assistant response
        B2ChatHistory::create([
            'user_id' => $user->id,
            'exercise_history_id' => $historyId,
            'role' => 'assistant',
            'content' => $response['content'],
            'context_type' => $request->context_type ?? 'general',
        ]);

        return response()->json([
            'reply' => $response['content'],
            'tokens_used' => $response['tokens_used'] ?? null,
        ]);
    }

    public function getChatHistory(Request $request)
    {
        $user = Auth::user();
        $historyId = $request->exercise_history_id;

        $messages = B2ChatHistory::where('user_id', $user->id)
            ->when($historyId, fn($q) => $q->where('exercise_history_id', $historyId))
            ->orderBy('created_at')
            ->limit(50)
            ->get(['role', 'content', 'context_type', 'created_at']);

        return response()->json(['messages' => $messages]);
    }

    private function buildSystemPrompt(?int $historyId): string
    {
        $base = "You are an expert Cambridge B2 English tutor. You help Spanish-speaking students understand their mistakes and improve their English. Always be encouraging, clear, and pedagogical. Keep responses concise (max 3-4 sentences unless asking for detailed explanation). Respond in Spanish unless the student writes in English.";

        if ($historyId) {
            $history = B2ExerciseHistory::with('exercise')->find($historyId);
            if ($history) {
                $exerciseInfo = $history->exercise
                    ? "Exercise: {$history->exercise->question_text}"
                    : '';
                $answerInfo = "Student answer: {$history->user_answer}. Correct: " . ($history->is_correct ? 'YES' : "NO, correct answer was: {$history->exercise?->correct_answer}");
                $base .= "\n\nContext: {$exerciseInfo}\n{$answerInfo}";
                if ($history->feedback_ia) {
                    $base .= "\nPrevious feedback: {$history->feedback_ia}";
                }
            }
        }

        return $base;
    }

    private function callClaudeApi(string $systemPrompt, array $history, string $userMessage): array
    {
        $apiKey = config('services.anthropic.key') ?? env('ANTHROPIC_API_KEY');

        if (!$apiKey) {
            return ['content' => $this->getFallbackResponse($userMessage), 'tokens_used' => 0];
        }

        try {
            $messages = array_merge($history, [['role' => 'user', 'content' => $userMessage]]);

            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-haiku-4-5-20251001',
                'max_tokens' => 512,
                'system' => $systemPrompt,
                'messages' => $messages,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'content' => $data['content'][0]['text'] ?? 'No response.',
                    'tokens_used' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
                ];
            }

            Log::warning('Claude API error in B2Chat', ['status' => $response->status(), 'body' => $response->body()]);
        } catch (\Exception $e) {
            Log::error('Claude API exception in B2Chat', ['error' => $e->getMessage()]);
        }

        return ['content' => $this->getFallbackResponse($userMessage), 'tokens_used' => 0];
    }

    private function getFallbackResponse(string $message): string
    {
        $lower = strtolower($message);

        if (str_contains($lower, 'por qué') || str_contains($lower, 'porque') || str_contains($lower, 'why')) {
            return "Revisa la explicación del ejercicio para entender por qué tu respuesta no era la correcta. Si necesitas más ayuda, consulta la sección de gramática o vocabulario relevante.";
        }

        if (str_contains($lower, 'ayuda') || str_contains($lower, 'help') || str_contains($lower, 'no entiendo')) {
            return "Para mejorar en este tipo de ejercicio, practica regularmente y presta atención a los patrones de error. ¿Quieres que te explique la regla gramatical específica?";
        }

        return "Soy tu tutor de inglés B2. Puedo ayudarte a entender por qué fallaste un ejercicio, explicar reglas gramaticales, o darte consejos para mejorar. ¿En qué puedo ayudarte?";
    }
}
