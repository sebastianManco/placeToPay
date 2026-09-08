<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $query = Category::query();

        if (! empty($search)) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $categories = $query->withCount('products')->orderBy('name')->paginate(10)->withQueryString();

        return view('admin.categories.index', compact('categories', 'search', 'status'));
    }

    /**
     * Show the form for creating a new category.
     */
    public function create(): View
    {
        return view('admin.categories.create');
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $category = Category::create($request->validated());

        return redirect()->route('admin.categories.index')
            ->with('success', "La categoría \"{$category->name}\" ha sido creada correctamente.");
    }

    /**
     * Show the form for editing the specified category.
     */
    public function edit(Category $category): View
    {
        $category->loadCount('products');

        return view('admin.categories.edit', compact('category'));
    }

    /**
     * Update the specified category in storage.
     */
    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $validated = $request->validated();

        if ($request->has('is_active')) {
            $validated['is_active'] = (bool) $request->input('is_active');
        }

        $category->update($validated);

        return redirect()->route('admin.categories.index')
            ->with('success', "La categoría \"{$category->name}\" ha sido actualizada correctamente.");
    }

    /**
     * Toggle category active status.
     */
    public function toggleStatus(Category $category): RedirectResponse
    {
        $category->is_active = ! $category->is_active;
        $category->save();

        $statusText = $category->is_active ? 'habilitada' : 'inhabilitada';

        return redirect()->back()->with('success', "La categoría \"{$category->name}\" ha sido {$statusText} correctamente.");
    }
}
