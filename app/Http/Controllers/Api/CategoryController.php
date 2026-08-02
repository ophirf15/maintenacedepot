<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request)
    {
        $query = Category::query()->withCount('toolTypes');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return response()->json([
            'data' => $query->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:64',
            'image_path' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:9',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $data['slug'] = $this->uniqueSlug($data['name']);

        $category = Category::query()->create($data);

        $this->audit->log('created', $category, null, $category->toArray());

        return response()->json(['data' => $category], 201);
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:160',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:64',
            'image_path' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:9',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $old = $category->toArray();
        $category->update($data);

        $this->audit->log('updated', $category, $old, $category->toArray());

        return response()->json(['data' => $category->fresh()]);
    }

    public function destroy(Category $category)
    {
        $old = $category->toArray();
        $category->delete();

        $this->audit->log('deleted', $category, $old, null);

        return response()->json(['ok' => true]);
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;
        while (Category::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
