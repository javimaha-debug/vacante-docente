<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper over the Anthropic Messages API: chat (Sonnet) and
 * handwritten-note OCR (Haiku Vision). Returns text + token usage so callers
 * can record cost in AiUsage.
 */
class AnthropicService
{
    /**
     * @param  array<int, array{role:string, content:string}>  $messages
     * @return array{text:string, tokens_input:int, tokens_output:int}
     */
    public function chat(array $messages, ?string $system = null, ?int $maxTokens = null): array
    {
        $payload = [
            'model' => config('ai.anthropic.chat_model'),
            'max_tokens' => $maxTokens ?? (int) config('ai.anthropic.max_tokens', 1500),
            'messages' => array_map(fn ($m) => [
                'role' => $m['role'],
                'content' => $m['content'],
            ], $messages),
        ];
        if ($system) {
            $payload['system'] = $system;
        }

        $json = $this->request($payload);

        return [
            'text' => $this->firstText($json),
            'tokens_input' => (int) ($json['usage']['input_tokens'] ?? 0),
            'tokens_output' => (int) ($json['usage']['output_tokens'] ?? 0),
        ];
    }

    /**
     * OCR a handwritten note image (raw bytes) with Haiku Vision.
     */
    public function ocrImage(string $imageBytes, string $mime): string
    {
        $payload = [
            'model' => config('ai.anthropic.vision_model'),
            'max_tokens' => 2000,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'image',
                        'source' => [
                            'type' => 'base64',
                            'media_type' => $mime ?: 'image/jpeg',
                            'data' => base64_encode($imageBytes),
                        ],
                    ],
                    [
                        'type' => 'text',
                        'text' => 'Extract all text from this image exactly as written. This is a handwritten study note. Return only the text, no commentary.',
                    ],
                ],
            ]],
        ];

        return $this->firstText($this->request($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(array $payload): array
    {
        return Http::withHeaders([
            'x-api-key' => (string) config('ai.anthropic.api_key'),
            'anthropic-version' => config('ai.anthropic.version'),
            'content-type' => 'application/json',
        ])->timeout(120)->post((string) config('ai.anthropic.endpoint'), $payload)
            ->throw()->json();
    }

    /** Pull the first text block out of a Messages API response. */
    private function firstText(array $json): string
    {
        foreach ($json['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                return trim((string) $block['text']);
            }
        }

        return '';
    }
}
