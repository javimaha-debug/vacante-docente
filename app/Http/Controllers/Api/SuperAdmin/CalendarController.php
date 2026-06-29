<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicCalendarEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    private const EVENT_TYPES = 'solicitud,listado_provisional,listado_definitivo,adjudicacion,plazo_alegaciones,resolucion,convocatoria,otro';

    /** All events (any visibility), optionally filtered. */
    public function index(Request $request): JsonResponse
    {
        $events = AcademicCalendarEvent::query()
            ->with('sourceDocument:id,title,status')
            ->when($request->query('visibility'), fn ($q, $v) => $q->where('visibility', $v))
            ->when($request->query('affects'), fn ($q, $v) => $q->where('affects', $v))
            ->when($request->query('event_type'), fn ($q, $v) => $q->where('event_type', $v))
            ->when($request->boolean('suggested'), fn ($q) => $q->whereNotNull('source_document_id')->where('is_confirmed', false))
            ->orderBy('event_date')
            ->get();

        return response()->json(['data' => $events]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request, true);
        $data['created_by'] = $request->user()->id;

        $event = AcademicCalendarEvent::create($data);

        return response()->json($event, 201);
    }

    public function update(Request $request, AcademicCalendarEvent $event): JsonResponse
    {
        $data = $this->validatePayload($request, false);
        $event->fill($data)->save();

        return response()->json($event->fresh());
    }

    public function destroy(AcademicCalendarEvent $event): JsonResponse
    {
        $event->delete();

        return response()->json(['deleted' => true]);
    }

    /** Confirm an estimated event as official. */
    public function confirm(AcademicCalendarEvent $event): JsonResponse
    {
        $event->forceFill(['is_confirmed' => true, 'is_estimated' => false])->save();

        return response()->json($event->fresh());
    }

    /** Make an event visible to users (or the public). */
    public function publish(Request $request, AcademicCalendarEvent $event): JsonResponse
    {
        $data = $request->validate(['visibility' => ['required', 'in:public,users_only']]);
        $event->forceFill(['visibility' => $data['visibility']])->save();

        return response()->json($event->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'title' => [$required, 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:2000'],
            'event_type' => [$required, 'in:'.self::EVENT_TYPES],
            'event_date' => [$required, 'date'],
            'time' => ['nullable', 'string', 'max:10'],
            'source_document_id' => ['nullable', 'integer', 'exists:detected_documents,id'],
            'is_confirmed' => ['sometimes', 'boolean'],
            'is_estimated' => ['sometimes', 'boolean'],
            'affects' => [$required, 'in:interinos,funcionarios,opositores,todos'],
            'visibility' => ['sometimes', 'in:public,users_only,superadmin_only'],
        ]);
    }
}
