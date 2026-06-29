<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicCalendarEvent;
use App\Models\DetectedDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    /**
     * Confirmed events visible to users (public + users_only), upcoming first.
     * Past events from the current cycle are still useful, so we include a short
     * tail but order upcoming ahead.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AcademicCalendarEvent::query()
            ->where('is_confirmed', true)
            ->whereIn('visibility', ['public', 'users_only'])
            ->when($request->query('affects'), fn ($q, $v) => $q->whereIn('affects', [$v, 'todos']))
            ->orderBy('event_date');

        $events = $query->get()->map(fn ($e) => $this->present($e));

        $upcoming = $events->filter(fn ($e) => $e['event_date'] >= now()->toDateString())->values();
        $past = $events->filter(fn ($e) => $e['event_date'] < now()->toDateString())->values();

        return response()->json([
            'data' => $events,
            'upcoming' => $upcoming,
            'past' => $past,
        ]);
    }

    /** Last 30 published documents (for the user-facing documents feed). */
    public function publishedDocuments(): JsonResponse
    {
        $docs = DetectedDocument::query()
            ->where('status', 'published')
            ->with('source:id,name,type')
            ->orderByDesc('published_at')
            ->limit(30)
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'title' => $d->title,
                'document_type' => $d->document_type,
                'published_at' => $d->published_at?->toDateString(),
                'source' => $d->source?->name,
                'pdf_url' => $d->pdf_url,
                'has_pdf' => (bool) ($d->pdf_url || $d->pdf_path),
            ]);

        return response()->json(['data' => $docs]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(AcademicCalendarEvent $e): array
    {
        return [
            'id' => $e->id,
            'title' => $e->title,
            'description' => $e->description,
            'event_type' => $e->event_type,
            'event_date' => $e->event_date?->toDateString(),
            'time' => $e->time,
            'is_estimated' => $e->is_estimated,
            'affects' => $e->affects,
        ];
    }
}
