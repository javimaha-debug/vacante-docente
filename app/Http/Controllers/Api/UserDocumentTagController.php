<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserDocumentTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserDocumentTagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => UserDocumentTag::where('user_id', $request->user()->id)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:9'],
        ]);

        $tag = UserDocumentTag::firstOrCreate(
            ['user_id' => $request->user()->id, 'name' => $data['name']],
            ['color' => $data['color'] ?? '#0e6e5e', 'created_at' => now()],
        );

        return response()->json($tag, 201);
    }

    public function destroy(Request $request, UserDocumentTag $tag): JsonResponse
    {
        abort_unless($tag->user_id === $request->user()->id, 403);
        $tag->delete();

        return response()->json(['deleted' => true]);
    }
}
