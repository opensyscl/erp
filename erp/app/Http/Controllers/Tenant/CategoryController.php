<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function __construct(
        protected CurrentTenant $currentTenant
    ) {}

    /**
     * Display a listing of categories.
     */
    public function index(): Response
    {
        $categories = Category::withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Tenant/Inventory/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        Category::create($validated);

        return back()->with('success', 'Categoría creada.');
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, string $categoryId)
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);



        $category = Category::findOrFail($categoryId);
        $category->update($validated);

        return back()->with('success', 'Categoría actualizada.');
    }

    /**
     * Remove the specified category.
     */
    public function destroy(string $categoryId)
    {
        $category = Category::findOrFail($categoryId);

        if ($category->products()->count() > 0) {
            return back()->with('error', 'No se puede eliminar una categoría con productos.');
        }

        $category->delete();

        return back()->with('success', 'Categoría eliminada.');
    }
}
