<?php

namespace App\Services;

use App\Models\DistanceCache;
use Illuminate\Support\Carbon;

/**
 * Reads/writes the distance_cache, matching on vacancy_id + home coordinates
 * rounded to 4 decimal places (~11 m precision) + travel mode.
 */
class DistanceCacheRepository
{
    public const PRECISION = 4;

    public function round(float $value): float
    {
        return round($value, self::PRECISION);
    }

    /**
     * Cached distances for a set of vacancies from a given home coordinate,
     * shaped as [vacancyId => [mode => payload]].
     *
     * @param  array<int>  $vacancyIds
     * @return array<int, array<string, array<string, mixed>>>
     */
    public function forVacancies(array $vacancyIds, float $homeLat, float $homeLng): array
    {
        if (empty($vacancyIds)) {
            return [];
        }

        $rows = DistanceCache::query()
            ->whereIn('vacancy_id', $vacancyIds)
            ->where('home_lat', $this->round($homeLat))
            ->where('home_lng', $this->round($homeLng))
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[$row->vacancy_id][$row->mode] = $this->payload($row);
        }

        return $out;
    }

    public function find(int $vacancyId, float $homeLat, float $homeLng, string $mode): ?DistanceCache
    {
        return DistanceCache::query()
            ->where('vacancy_id', $vacancyId)
            ->where('home_lat', $this->round($homeLat))
            ->where('home_lng', $this->round($homeLng))
            ->where('mode', $mode)
            ->first();
    }

    /**
     * @param  array{duration_minutes: int|null, distance_km: float|null, traffic_note: string|null}  $result
     */
    public function store(int $vacancyId, float $homeLat, float $homeLng, string $mode, array $result): DistanceCache
    {
        return DistanceCache::updateOrCreate(
            [
                'vacancy_id' => $vacancyId,
                'home_lat' => $this->round($homeLat),
                'home_lng' => $this->round($homeLng),
                'mode' => $mode,
            ],
            [
                'duration_minutes' => $result['duration_minutes'],
                'distance_km' => $result['distance_km'],
                'traffic_note' => $result['traffic_note'],
                'calculated_at' => Carbon::now(),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(DistanceCache $row): array
    {
        return [
            'duration_minutes' => $row->duration_minutes,
            'distance_km' => $row->distance_km !== null ? (float) $row->distance_km : null,
            'traffic_note' => $row->traffic_note,
            'calculated_at' => $row->calculated_at?->toIso8601String(),
        ];
    }
}
