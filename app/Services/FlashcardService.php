<?php

namespace App\Services;

use App\Models\OposicionTema;
use App\Models\UserDocument;

class FlashcardService
{
    public function __construct(
        private readonly AnthropicService $anthropic,
        private readonly RagService $rag,
    ) {}

    /**
     * Generate flashcards for a tema, grounded in the user's linked documents
     * when available.
     *
     * @return array<int, array{pregunta:string, respuesta:string, dificultad:string, fuente:?string}>
     */
    public function generateFromTema(int $userId, OposicionTema $tema, int $count = 10): array
    {
        $docIds = UserDocument::where('user_id', $userId)->where('tema_id', $tema->id)->pluck('id')->all();
        $context = '';
        if (! empty($docIds)) {
            $chunks = $this->rag->search($tema->titulo, $userId, $docIds, 8);
            $context = $this->rag->buildContext($chunks);
        }

        return $this->generate($count, $tema->titulo, $tema->especialidad_code, $context);
    }

    /**
     * @return array<int, array{pregunta:string, respuesta:string, dificultad:string, fuente:?string}>
     */
    public function generateFromDocument(int $userId, UserDocument $document, int $count = 10): array
    {
        $chunks = $this->rag->search($document->name, $userId, [$document->id], 8);
        $context = $this->rag->buildContext($chunks);

        return $this->generate($count, $document->name, null, $context);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function generate(int $count, string $title, ?string $especialidad, string $context): array
    {
        $count = max(1, min(30, $count));
        $esp = $especialidad ?: 'general';
        $ragBlock = $context !== '' ? "Basándote en estos apuntes del opositor:\n{$context}\n" : '';

        $prompt = <<<PROMPT
        Genera {$count} flashcards de estudio para el tema: '{$title}'
        Especialidad: {$esp}

        {$ragBlock}
        Responde ÚNICAMENTE con JSON válido, sin texto adicional:
        [{"pregunta":"...","respuesta":"...","dificultad":"basica|media|avanzada","fuente":"nombre o null"}]
        PROMPT;

        $result = $this->anthropic->chat(
            [['role' => 'user', 'content' => $prompt]],
            'Eres un generador de flashcards de estudio. Devuelves siempre JSON válido y nada más.',
            2000,
        );

        return $this->parse($result['text']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parse(string $text): array
    {
        // Tolerate code fences / surrounding prose: grab the first JSON array.
        if (preg_match('/\[\s*\{.*\}\s*\]/s', $text, $m)) {
            $text = $m[0];
        }
        $data = json_decode($text, true);
        if (! is_array($data)) {
            return [];
        }

        return collect($data)
            ->filter(fn ($c) => is_array($c) && ! empty($c['pregunta']) && ! empty($c['respuesta']))
            ->map(fn ($c) => [
                'pregunta' => (string) $c['pregunta'],
                'respuesta' => (string) $c['respuesta'],
                'dificultad' => in_array($c['dificultad'] ?? '', ['basica', 'media', 'avanzada'], true) ? $c['dificultad'] : 'media',
                'fuente' => ! empty($c['fuente']) && $c['fuente'] !== 'null' ? (string) $c['fuente'] : null,
            ])->values()->all();
    }
}
