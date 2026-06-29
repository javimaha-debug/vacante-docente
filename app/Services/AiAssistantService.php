<?php

namespace App\Services;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiUsage;
use App\Models\NormativaDocumento;
use App\Models\OposicionTema;
use App\Models\TemaOficial;
use App\Models\TemarioOficial;
use App\Models\User;
use Illuminate\Support\Carbon;

class AiAssistantService
{
    public function __construct(
        private readonly AnthropicService $anthropic,
        private readonly RagService $rag,
    ) {}

    /** Whether the user is under the daily safety cap. */
    public function withinDailyLimit(int $userId): bool
    {
        $limit = (int) config('ai.daily_message_limit', 500);
        $usage = AiUsage::where('user_id', $userId)->whereDate('date', Carbon::now()->toDateString())->first();

        return ! $usage || $usage->messages_count < $limit;
    }

    /**
     * Send a user message in a conversation and get the assistant's reply.
     *
     * @param  array{document_ids?: array<int,int>}  $options
     * @return array{message: AiMessage, citations: array<int, array<string,mixed>>}
     */
    public function chat(User $user, AiConversation $conversation, string $userMessage, array $options = []): array
    {
        $history = $conversation->messages()->orderByDesc('id')->limit(10)->get()->reverse()->values();

        // Retrieve context per the conversation's context_type.
        $citations = [];
        $contextBlock = '';
        if ($conversation->context_type === 'document') {
            $chunks = $this->rag->search($userMessage, $user->id, $options['document_ids'] ?? null);
            $citations = array_map(fn ($c) => [
                'id' => $c['id'], 'document_name' => $c['document_name'], 'page_number' => $c['page_number'],
            ], $chunks);
            $contextBlock = $this->rag->buildContext($chunks);
        }

        $messages = [];
        foreach ($history as $m) {
            $messages[] = ['role' => $m->role, 'content' => $m->content];
        }
        $userContent = $contextBlock !== ''
            ? "Contexto de mis apuntes:\n{$contextBlock}\n\n---\nPregunta: {$userMessage}"
            : $userMessage;
        $messages[] = ['role' => 'user', 'content' => $userContent];

        $result = $this->anthropic->chat($messages, $this->systemPrompt($user, $conversation));

        // Persist both turns.
        $now = Carbon::now();
        AiMessage::create([
            'conversation_id' => $conversation->id, 'role' => 'user',
            'content' => $userMessage, 'created_at' => $now,
        ]);
        $assistant = AiMessage::create([
            'conversation_id' => $conversation->id, 'role' => 'assistant',
            'content' => $result['text'] !== '' ? $result['text'] : 'No he podido generar una respuesta.',
            'chunks_used' => $citations ?: null,
            'tokens_input' => $result['tokens_input'],
            'tokens_output' => $result['tokens_output'],
            'created_at' => $now,
        ]);

        $conversation->forceFill([
            'title' => $conversation->title ?: mb_substr($userMessage, 0, 60),
            'updated_at' => $now,
        ])->save();

        $this->recordUsage($user->id, $result['tokens_input'], $result['tokens_output']);

        return ['message' => $assistant, 'citations' => $citations];
    }

    /** Build the study-assistant system prompt with the user's temario + normativa. */
    public function systemPrompt(User $user, AiConversation $conversation): string
    {
        $especialidad = $conversation->especialidad_code ?: 'general';

        $temas = OposicionTema::where('user_id', $user->id)
            ->when($conversation->especialidad_code, fn ($q, $c) => $q->where('especialidad_code', $c))
            ->orderBy('numero')->get();
        $dominados = $temas->where('status', 'dominado')->count();

        $temasList = $temas->isNotEmpty()
            ? $temas->map(fn ($t) => "- T{$t->numero} {$t->titulo} ({$t->status})")->implode("\n")
            : '(sin temario cargado)';

        $normativa = '(sin normativa cargada)';
        try {
            $docs = NormativaDocumento::query()->where('vigente', true)->limit(8)->pluck('titulo');
            if ($docs->isNotEmpty()) {
                $normativa = $docs->map(fn ($t) => "- {$t}")->implode("\n");
            }
        } catch (\Throwable $e) {
            // Normativa table/columns are optional context.
        }

        // When studying a specific official tema, load its esquema + keywords so
        // the assistant has the official knowledge base even without user notes.
        $temaOficialBlock = $this->officialTemaContext($conversation);

        return <<<PROMPT
        Eres un asistente de estudio para oposiciones docentes en España.
        Ayudas a preparar la especialidad: {$especialidad}.
        El opositor tiene {$temas->count()} temas, de los que domina {$dominados}.

        TEMAS DEL OPOSITOR:
        {$temasList}

        NORMATIVA RELEVANTE:
        {$normativa}
        {$temaOficialBlock}
        INSTRUCCIONES:
        - Responde siempre en español
        - Sé conciso y pedagógico
        - Cita siempre la fuente exacta: [Fuente: 'nombre', p.X]
        - No generes programaciones didácticas completas
        - Nunca aceptes datos de alumnos identificables
        - Si no sabes algo, dilo claramente
        - Posiciónate siempre como asistente, no como autor
        PROMPT;
    }

    /**
     * Build the official-tema knowledge block for the system prompt, if the
     * conversation is scoped to a specific tema number.
     */
    private function officialTemaContext(AiConversation $conversation): string
    {
        if (! $conversation->tema_numero || ! $conversation->especialidad_code) {
            return '';
        }

        $temario = TemarioOficial::where('especialidad_code', $conversation->especialidad_code)->first();
        if (! $temario) {
            return '';
        }

        $tema = TemaOficial::where('temario_id', $temario->id)
            ->where('numero', $conversation->tema_numero)->first();
        if (! $tema) {
            return '';
        }

        $esquema = '';
        foreach ((array) $tema->esquema as $punto) {
            $titulo = is_array($punto) ? ($punto['punto'] ?? '') : (string) $punto;
            $esquema .= "- {$titulo}\n";
            foreach ((array) ($punto['subpuntos'] ?? []) as $sub) {
                $esquema .= "    · {$sub}\n";
            }
        }
        $keywords = implode(', ', (array) $tema->keywords);

        return "\nTEMA OFICIAL EN ESTUDIO — Tema {$tema->numero}: {$tema->titulo}\n"
            ."ESQUEMA OFICIAL:\n".($esquema !== '' ? $esquema : "(sin esquema)\n")
            .($keywords !== '' ? "PALABRAS CLAVE: {$keywords}\n" : '');
    }

    public function recordUsage(int $userId, int $tokensIn, int $tokensOut, int $voyageCalls = 0): void
    {
        $usage = AiUsage::firstOrCreate(
            ['user_id' => $userId, 'date' => Carbon::now()->toDateString()],
            ['messages_count' => 0, 'tokens_input' => 0, 'tokens_output' => 0, 'voyage_calls' => 0],
        );
        $usage->increment('messages_count');
        $usage->increment('tokens_input', $tokensIn);
        $usage->increment('tokens_output', $tokensOut);
        if ($voyageCalls > 0) {
            $usage->increment('voyage_calls', $voyageCalls);
        }
    }
}
