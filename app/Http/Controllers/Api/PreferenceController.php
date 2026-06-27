<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkPreferencesRequest;
use App\Http\Resources\PreferenceResource;
use App\Models\UserList;
use App\Models\UserVacancyPreference;
use App\Services\DistanceCacheRepository;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PreferenceController extends Controller
{
    public function __construct(private readonly DistanceCacheRepository $distances) {}

    /**
     * All preferences for a list, with vacancy details and cached distances.
     * Ordered: selected (by position) → neutral → discarded.
     */
    public function index(UserList $userList): AnonymousResourceCollection
    {
        $preferences = $userList->preferences()
            ->with('vacancy')
            ->orderByRaw("FIELD(status, 'selected', 'neutral', 'discarded')")
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $distanceMap = [];
        if ($userList->hasHome()) {
            $distanceMap = $this->distances->forVacancies(
                $preferences->pluck('vacancy_id')->all(),
                (float) $userList->home_lat,
                (float) $userList->home_lng,
            );
        }

        return PreferenceResource::collection(
            $preferences->map(fn (UserVacancyPreference $pref) => (new PreferenceResource($pref))
                ->withDistances($distanceMap[$pref->vacancy_id] ?? null))
        );
    }

    /**
     * Upsert all provided preferences in a single transaction.
     */
    public function bulk(BulkPreferencesRequest $request, UserList $userList): AnonymousResourceCollection
    {
        $rows = $request->validated()['preferences'];

        DB::transaction(function () use ($rows, $userList) {
            foreach ($rows as $row) {
                UserVacancyPreference::updateOrCreate(
                    [
                        'user_list_id' => $userList->id,
                        'vacancy_id' => $row['vacancy_id'],
                    ],
                    [
                        'position' => $row['position'] ?? 0,
                        'status' => $row['status'],
                        'notes' => $row['notes'] ?? null,
                    ]
                );
            }
        });

        return $this->index($userList);
    }
}
