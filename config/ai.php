<?php

return [
    /*
    | Voyage AI — embeddings for RAG. The first 200M tokens are free, so the
    | trial phase is effectively free. We call the REST API directly (testable
    | via Http::fake) rather than the SDK.
    */
    'voyage' => [
        'api_key' => env('VOYAGE_AI_API_KEY'),
        'model' => env('VOYAGE_AI_MODEL', 'voyage-3'),
        'dimensions' => (int) env('VOYAGE_AI_EMBEDDING_DIMENSIONS', 1024),
        'endpoint' => env('VOYAGE_AI_ENDPOINT', 'https://api.voyageai.com/v1/embeddings'),
        'batch' => 128,
    ],

    /*
    | Anthropic — chat (Sonnet) + handwritten-note OCR (Haiku Vision).
    */
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'endpoint' => env('ANTHROPIC_ENDPOINT', 'https://api.anthropic.com/v1/messages'),
        'version' => '2023-06-01',
        'chat_model' => env('ANTHROPIC_CHAT_MODEL', 'claude-sonnet-4-6'),
        'vision_model' => env('ANTHROPIC_VISION_MODEL', 'claude-haiku-4-5-20251001'),
        'max_tokens' => 1500,
    ],

    // Hard safety cap on assistant messages per user per day (cost protection).
    'daily_message_limit' => (int) env('AI_DAILY_MESSAGE_LIMIT', 500),

    // RAG retrieval defaults.
    'rag' => [
        'similarity_threshold' => 0.7,
        'default_limit' => 6,
        'chunk_tokens' => 400,
        'chunk_overlap' => 50,
        'min_chunk_tokens' => 50,
    ],
];
