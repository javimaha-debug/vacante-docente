<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SpecialtyResource;
use App\Models\Specialty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpecialtyController extends Controller
{
    /**
     * List all specialties grouped by education level, with vacancy counts.
     */
    public function index(Request $request): JsonResponse
    {
        $year = (int) $request->integer('year', 2025);

        $specialties = Specialty::query()
            ->withCount(['vacancies' => fn ($q) => $q->where('year', $year)])
            ->orderBy('code')
            ->get()
            ->groupBy('education_level');

        $shape = fn (string $level) => SpecialtyResource::collection(
            $specialties->get($level, collect())->values()
        );

        return response()->json([
            'maestros' => $shape('maestros'),
            'secundaria' => $shape('secundaria'),
            'fp' => $shape('fp'),
        ]);
    }
}
