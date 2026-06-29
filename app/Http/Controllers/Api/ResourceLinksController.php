<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ResourceLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourceLinksController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => ResourceLink::active()->ordered()->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'url' => ['required', 'url', 'max:500'],
            'category' => ['required', 'in:oficial,sindicato,otro'],
            'icon' => ['nullable', 'string', 'max:16'],
            'position' => ['nullable', 'integer', 'min:0'],
            'active' => ['boolean'],
        ]);

        return response()->json(ResourceLink::create($data), 201);
    }

    public function update(Request $request, ResourceLink $resourceLink): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'url' => ['sometimes', 'required', 'url', 'max:500'],
            'category' => ['sometimes', 'in:oficial,sindicato,otro'],
            'icon' => ['nullable', 'string', 'max:16'],
            'position' => ['nullable', 'integer', 'min:0'],
            'active' => ['boolean'],
        ]);

        $resourceLink->update($data);

        return response()->json($resourceLink);
    }

    public function destroy(ResourceLink $resourceLink): JsonResponse
    {
        $resourceLink->delete();

        return response()->json(['deleted' => true]);
    }
}
