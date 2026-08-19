<?php

namespace App\Http\Controllers;

use App\Models\B2Resource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class B2ResourcesController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $type = $request->query('type');
        $category = $request->query('category');

        $query = B2Resource::where('is_active', true);

        if ($type) {
            $query->where('type', $type);
        }
        if ($category) {
            $query->where('category', $category);
        }

        $resources = $query->orderBy('type')->orderBy('title')->get();

        // Mark favorites
        $favoriteIds = $user->load('b2ResourceFavorites')->b2ResourceFavorites->pluck('id')->toArray();

        $resources = $resources->map(fn($r) => array_merge($r->toArray(), [
            'is_favorite' => in_array($r->id, $favoriteIds),
        ]));

        return response()->json([
            'resources' => $resources,
            'types' => B2Resource::where('is_active', true)->distinct()->pluck('type'),
        ]);
    }

    public function toggleFavorite(Request $request, int $resourceId)
    {
        $user = Auth::user();
        $resource = B2Resource::findOrFail($resourceId);

        $exists = \DB::table('b2_user_resources_favorites')
            ->where('user_id', $user->id)
            ->where('resource_id', $resourceId)
            ->exists();

        if ($exists) {
            \DB::table('b2_user_resources_favorites')
                ->where('user_id', $user->id)
                ->where('resource_id', $resourceId)
                ->delete();
            $isFavorite = false;
        } else {
            \DB::table('b2_user_resources_favorites')->insert([
                'user_id' => $user->id,
                'resource_id' => $resourceId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $isFavorite = true;
        }

        return response()->json(['is_favorite' => $isFavorite]);
    }

    public function getFavorites()
    {
        $user = Auth::user();

        $resources = B2Resource::whereIn('id',
            \DB::table('b2_user_resources_favorites')
                ->where('user_id', $user->id)
                ->pluck('resource_id')
        )->get()->map(fn($r) => array_merge($r->toArray(), ['is_favorite' => true]));

        return response()->json(['resources' => $resources]);
    }
}
