<?php

namespace App\Http\Controllers\Api\Docente;

use App\Http\Controllers\Controller;
use App\Models\DocenteAsignatura;
use App\Models\DocenteGrupo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GruposController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'asignatura_id' => ['required', 'integer'],
            'nombre' => ['required', 'string', 'max:20'],
            'num_alumnos' => ['nullable', 'integer', 'min:1', 'max:60'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $asignatura = DocenteAsignatura::where('user_id', $request->user()->id)
            ->findOrFail($data['asignatura_id']);

        $grupo = DocenteGrupo::create(array_merge($data, ['user_id' => $request->user()->id]));

        return response()->json($grupo, 201);
    }

    public function update(Request $request, DocenteGrupo $grupo): JsonResponse
    {
        abort_if($grupo->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:20'],
            'num_alumnos' => ['nullable', 'integer', 'min:1', 'max:60'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $grupo->update($data);

        return response()->json($grupo);
    }

    public function destroy(Request $request, DocenteGrupo $grupo): JsonResponse
    {
        abort_if($grupo->user_id !== $request->user()->id, 403);
        $grupo->delete();

        return response()->json(['deleted' => true]);
    }
}
