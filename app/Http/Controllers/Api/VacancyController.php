<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\VacancyResource;
use App\Models\UserList;
use App\Models\Vacancy;
use App\Services\DistanceCacheRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VacancyController extends Controller
{
    public function __construct(private readonly DistanceCacheRepository $distances) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'specialty_id' => ['required', 'integer', 'exists:specialties,id'],
            'year' => ['sometimes', 'integer'],
            'provincia' => ['sometimes', 'in:Alacant,Castelló,València'],
            'tipo_centro' => ['sometimes', 'array'],
            'tipo_centro.*' => ['in:Secundaria,Primaria/Infantil,Otro'],
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],
            'session_token' => ['sometimes', 'nullable', 'string', 'max:64'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ]);

        $year = (int) ($validated['year'] ?? 2025);

        $query = Vacancy::query()
            ->where('specialty_id', $validated['specialty_id'])
            ->where('year', $year);

        if (! empty($validated['provincia'])) {
            $query->where('provincia', $validated['provincia']);
        }

        if (! empty($validated['tipo_centro'])) {
            $query->whereIn('tipo_centro', $validated['tipo_centro']);
        }

        if (! empty($validated['search'])) {
            $term = '%'.$validated['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('localidad', 'like', $term)
                    ->orWhere('centro_nombre', 'like', $term);
            });
        }

        // Tags: req_ling is a column; the rest live in observ_tags JSON.
        foreach ($validated['tags'] ?? [] as $tag) {
            if ($tag === 'Req. lingüístico' || $tag === 'req_ling') {
                $query->where('req_ling', true);
            } else {
                $query->whereJsonContains('observ_tags', $tag);
            }
        }

        $perPage = (int) ($validated['per_page'] ?? 100);

        $paginator = $query
            ->orderBy('provincia')
            ->orderBy('localidad')
            ->orderBy('centro_nombre')
            ->paginate($perPage)
            ->withQueryString();

        // Attach cached distances when the caller has a geocoded home.
        $distanceMap = $this->resolveDistances($validated['session_token'] ?? null, $validated['specialty_id'], $paginator->getCollection()->modelKeys());

        $paginator->getCollection()->transform(function (Vacancy $vacancy) use ($distanceMap) {
            return (new VacancyResource($vacancy))->withDistances($distanceMap[$vacancy->id] ?? null);
        });

        return VacancyResource::collection($paginator);
    }

    /**
     * @param  array<int>  $vacancyIds
     * @return array<int, array<string, mixed>>
     */
    private function resolveDistances(?string $sessionToken, int $specialtyId, array $vacancyIds): array
    {
        if (! $sessionToken || empty($vacancyIds)) {
            return [];
        }

        $list = UserList::query()
            ->where('session_token', $sessionToken)
            ->where('specialty_id', $specialtyId)
            ->first();

        if (! $list || ! $list->hasHome()) {
            return [];
        }

        return $this->distances->forVacancies($vacancyIds, (float) $list->home_lat, (float) $list->home_lng);
    }
}
