<?php

namespace Tests\Feature;

use App\Jobs\ChunkDocumentJob;
use App\Jobs\ExtractDocumentTextJob;
use App\Models\AiConversation;
use App\Models\AiUsage;
use App\Models\DocumentChunk;
use App\Models\OposicionTema;
use App\Models\User;
use App\Models\UserDocument;
use App\Services\EmbeddingService;
use App\Services\RagService;
use App\Services\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RagAssistantTest extends TestCase
{
    use RefreshDatabase;

    /** Fake Voyage so every embed() returns a fixed 3-dim vector. */
    private function fakeVoyage(array $vector = [1.0, 0.0, 0.0]): void
    {
        Http::fake([
            'api.voyageai.com/*' => Http::response(['data' => [['index' => 0, 'embedding' => $vector]]], 200),
        ]);
    }

    private function fakeAnthropic(string $text): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => $text]],
                'usage' => ['input_tokens' => 42, 'output_tokens' => 17],
            ], 200),
        ]);
    }

    private function chunk(User $u, UserDocument $d, string $content, array $vec): DocumentChunk
    {
        return DocumentChunk::create([
            'user_document_id' => $d->id, 'user_id' => $u->id, 'chunk_index' => 0,
            'page_number' => 1, 'content' => $content, 'token_count' => 10,
            'embedding' => EmbeddingService::toVectorLiteral($vec),
        ]);
    }

    public function test_rag_search_returns_nearest_chunk_with_citation_context(): void
    {
        $user = User::factory()->create();
        $doc = UserDocument::create(['user_id' => $user->id, 'name' => 'Tema 3.pdf', 'disk_path' => 'p', 'type' => 'pdf', 'size_bytes' => 1]);

        $this->chunk($user, $doc, 'La Constitución de 1978 reconoce el derecho a la educación.', [1.0, 0.0, 0.0]);
        $this->chunk($user, $doc, 'Texto sin relación sobre cocina.', [0.0, 1.0, 0.0]);

        $this->fakeVoyage([1.0, 0.0, 0.0]); // query aligned with the first chunk

        $rag = app(RagService::class);
        $results = $rag->search('derecho a la educación', $user->id);

        $this->assertNotEmpty($results);
        $this->assertSame('La Constitución de 1978 reconoce el derecho a la educación.', $results[0]['content']);
        $this->assertGreaterThan(0.7, $results[0]['similarity']);

        $context = $rag->buildContext($results);
        $this->assertStringContainsString('[Fuente: "Tema 3.pdf", página 1]', $context);
    }

    public function test_pipeline_extracts_chunks_and_embeds_an_image_document(): void
    {
        Storage::fake(config('documents.disk'));
        $this->fakeVoyage([0.2, 0.4, 0.6]);
        $this->fakeAnthropic('Apuntes: la jerarquía normativa española.'); // OCR result

        $user = User::factory()->create();
        Storage::disk(config('documents.disk'))->put('p/note.png', 'imgbytes');
        $doc = UserDocument::create([
            'user_id' => $user->id, 'name' => 'note.png', 'disk_path' => 'p/note.png',
            'type' => 'image', 'mime_type' => 'image/png', 'size_bytes' => 8, 'processing_status' => 'pending',
        ]);

        // Sync queue → the whole chain runs.
        (new ExtractDocumentTextJob($doc->id))->handle(app(\App\Services\AnthropicService::class));

        $this->assertSame('ready', $doc->fresh()->processing_status);
        $chunks = DocumentChunk::where('user_document_id', $doc->id)->get();
        $this->assertNotEmpty($chunks);
        $this->assertNotNull($chunks->first()->embedding);
    }

    public function test_chunk_text_respects_token_budget_with_overlap(): void
    {
        $job = new ChunkDocumentJob(0);
        $words = implode(' ', array_fill(0, 1000, 'palabra'));
        $chunks = $job->chunkText($words, 400, 50);

        $this->assertGreaterThan(1, count($chunks)); // long text → multiple chunks
        foreach ($chunks as $c) {
            $this->assertLessThanOrEqual(600, str_word_count($c)); // ~perChunk words
        }
    }

    public function test_chat_free_context_saves_messages_and_tracks_usage(): void
    {
        $this->fakeAnthropic('La respuesta del asistente.');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $conv = AiConversation::create(['user_id' => $user->id, 'mode' => 'chat', 'context_type' => 'free']);

        $this->postJson("/api/v1/ai/conversations/{$conv->id}/message", ['message' => '¿Qué es la LOMLOE?'])
            ->assertOk()
            ->assertJsonPath('message.role', 'assistant')
            ->assertJsonPath('message.content', 'La respuesta del asistente.');

        $this->assertSame(2, $conv->messages()->count()); // user + assistant
        $this->assertSame(1, (int) AiUsage::where('user_id', $user->id)->first()->messages_count);
    }

    public function test_chat_document_context_returns_citations(): void
    {
        $this->fakeVoyage([1.0, 0.0, 0.0]);
        // Voyage + Anthropic share the fake; add anthropic response too.
        Http::fake([
            'api.voyageai.com/*' => Http::response(['data' => [['index' => 0, 'embedding' => [1.0, 0.0, 0.0]]]], 200),
            'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Según tus apuntes...']], 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]], 200),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $doc = UserDocument::create(['user_id' => $user->id, 'name' => 'Apuntes.pdf', 'disk_path' => 'p', 'type' => 'pdf', 'size_bytes' => 1]);
        $this->chunk($user, $doc, 'Contenido relevante del tema.', [1.0, 0.0, 0.0]);

        $conv = AiConversation::create(['user_id' => $user->id, 'mode' => 'chat', 'context_type' => 'document']);

        $res = $this->postJson("/api/v1/ai/conversations/{$conv->id}/message", [
            'message' => 'Resume el tema', 'document_ids' => [$doc->id],
        ])->assertOk()->json();

        $this->assertNotEmpty($res['citations']);
        $this->assertSame('Apuntes.pdf', $res['citations'][0]['document_name']);
    }

    public function test_daily_message_limit_returns_429(): void
    {
        $this->fakeAnthropic('x');
        config(['ai.daily_message_limit' => 1]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        AiUsage::create(['user_id' => $user->id, 'date' => now()->toDateString(), 'messages_count' => 1]);

        $conv = AiConversation::create(['user_id' => $user->id, 'mode' => 'chat', 'context_type' => 'free']);
        $this->postJson("/api/v1/ai/conversations/{$conv->id}/message", ['message' => 'hola'])->assertStatus(429);
    }

    public function test_cannot_message_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $conv = AiConversation::create(['user_id' => $owner->id, 'mode' => 'chat', 'context_type' => 'free']);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v1/ai/conversations/{$conv->id}/message", ['message' => 'hi'])->assertForbidden();
    }

    public function test_flashcards_from_tema_parses_json(): void
    {
        $this->fakeAnthropic('[{"pregunta":"¿Qué es la LOMLOE?","respuesta":"Una ley","dificultad":"basica","fuente":null}]');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $tema = OposicionTema::create(['user_id' => $user->id, 'especialidad_code' => '590', 'numero' => 1, 'titulo' => 'Normativa', 'status' => 'pendiente']);

        $res = $this->postJson('/api/v1/ai/flashcards/from-tema', ['tema_id' => $tema->id, 'count' => 1])
            ->assertOk()->json();

        $this->assertCount(1, $res['data']);
        $this->assertSame('¿Qué es la LOMLOE?', $res['data'][0]['pregunta']);
        $this->assertSame('basica', $res['data'][0]['dificultad']);
    }

    public function test_scoring_updates_and_recommends(): void
    {
        $user = User::factory()->create();
        $tema = OposicionTema::create(['user_id' => $user->id, 'especialidad_code' => '590', 'numero' => 1, 'titulo' => 'T1', 'status' => 'pendiente']);

        $scoring = app(ScoringService::class);
        $scoring->updateTemaScore($tema, 'simulacro', ['correct' => 2, 'total' => 10]); // 20%

        $present = $scoring->present($tema->fresh());
        $this->assertSame(20, $present['score']);
        $this->assertStringContainsString('Prioritario', $present['recommendation']);

        $scores = $scoring->getTemaScores($user->id);
        $this->assertSame($tema->id, $scores[0]['tema_id']); // worst first
    }

    public function test_flashcard_result_updates_score(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $tema = OposicionTema::create(['user_id' => $user->id, 'especialidad_code' => '590', 'numero' => 1, 'titulo' => 'T1', 'status' => 'pendiente']);

        $this->postJson('/api/v1/ai/flashcards/result', ['tema_id' => $tema->id, 'correct' => 9, 'total' => 10])
            ->assertOk()->assertJsonPath('score', 90);
    }

    public function test_superadmin_ai_usage_requires_superadmin(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/superadmin/ai-usage')->assertForbidden();

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'superadmin'])->save();
        Sanctum::actingAs($admin->fresh());

        AiUsage::create(['user_id' => $admin->id, 'date' => now()->toDateString(), 'messages_count' => 5, 'tokens_input' => 1000, 'tokens_output' => 500]);

        $this->getJson('/api/v1/superadmin/ai-usage')
            ->assertOk()
            ->assertJsonStructure(['mensajes' => ['hoy', 'semana', 'mes'], 'coste_estimado_usd', 'top_usuarios', 'serie_diaria']);
    }
}
