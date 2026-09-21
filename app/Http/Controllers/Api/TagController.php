<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTagRequest;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for managing tags.
 * Protected by Sanctum or API key middleware.
 */
class TagController extends Controller
{
    /**
     * List all tags with lead counts.
     * 
     * GET /api/v1/tags
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tag::query()->withCount('leads');

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('slug', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        $tags = $query->orderBy('name')->get();

        return response()->json([
            'data' => $tags,
        ]);
    }

    /**
     * Create a new custom tag.
     * 
     * POST /api/v1/tags
     */
    public function store(StoreTagRequest $request): JsonResponse
    {
        $tag = Tag::create($request->validated());

        return response()->json([
            'message' => 'Tag created successfully.',
            'data'    => $tag,
        ], 201);
    }

    /**
     * Get a single tag.
     * 
     * GET /api/v1/tags/{id}
     */
    public function show(int $id): JsonResponse
    {
        $tag = Tag::withCount('leads')->findOrFail($id);

        return response()->json([
            'data' => $tag,
        ]);
    }

    /**
     * Delete a tag.
     * 
     * DELETE /api/v1/tags/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $tag = Tag::findOrFail($id);
        $tag->delete();

        return response()->json([
            'message' => "Tag '{$tag->name}' deleted successfully.",
        ]);
    }
}
