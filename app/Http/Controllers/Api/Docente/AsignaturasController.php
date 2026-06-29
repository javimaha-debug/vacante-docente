<?php

namespace App\Http\Controllers\Api\Docente;

use App\Http\Controllers\Controller;
use App\Models\DocenteAsignatura;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AsignaturasController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $anyo = $request->query('año_academico', $this->currentAnyo());
        $asignaturas = DocenteAsignatura::with('grupos')
            ->where('user_id', $request->user()->id)
            ->where('año_academico', $anyo)
            ->orderBy('etapa')->orderBy('curso')->orderBy('nombre')
            ->get();

        return response()->json(['data' => $asignaturas]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'codigo_asignatura' => ['nullable', 'string', 'max:20'],
            'etapa' => ['required', 'in:infantil,primaria,eso,bachillerato,fp,otros'],
            'curso' => ['required', 'string', 'max:30'],
            'año_academico' => ['required', 'string', 'max:9'],
        ]);

        $asignatura = DocenteAsignatura::create(array_merge($data, ['user_id' => $request->user()->id]));

        return response()->json($asignatura->load('grupos'), 201);
    }

    public function update(Request $request, DocenteAsignatura $asignatura): JsonResponse
    {
        abort_if($asignatura->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:100'],
            'codigo_asignatura' => ['nullable', 'string', 'max:20'],
            'etapa' => ['sometimes', 'in:infantil,primaria,eso,bachillerato,fp,otros'],
            'curso' => ['sometimes', 'string', 'max:30'],
            'año_academico' => ['sometimes', 'string', 'max:9'],
        ]);

        $asignatura->update($data);

        return response()->json($asignatura->load('grupos'));
    }

    public function destroy(Request $request, DocenteAsignatura $asignatura): JsonResponse
    {
        abort_if($asignatura->user_id !== $request->user()->id, 403);
        $asignatura->delete();

        return response()->json(['deleted' => true]);
    }

    public function grupos(Request $request, DocenteAsignatura $asignatura): JsonResponse
    {
        abort_if($asignatura->user_id !== $request->user()->id, 403);

        return response()->json(['data' => $asignatura->grupos]);
    }

    private function currentAnyo(): string
    {
        $year = (int) Carbon::now()->format('Y');
        $month = (int) Carbon::now()->format('n');

        return $month >= 9 ? "{$year}-" . ($year + 1) : ($year - 1) . "-{$year}";
    }
}
