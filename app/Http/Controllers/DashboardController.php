<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductSearchRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the product showcase dashboard for registered clients with custom search and filters.
     */
    public function index(ProductSearchRequest $request): View
    {
        $filters = $request->validated();
        $search = $request->query('search');
        $categoryId = $request->query('category');
        $title = $request->query('title', 'Vitrina de Productos');

        $products = Product::with('category')
            ->where('is_active', true)
            ->filter($filters)
            ->paginate(12)
            ->withQueryString();

        $categories = Category::where('is_active', true)->orderBy('name')->get();

        return view('dashboard', compact('products', 'categories', 'filters', 'search', 'categoryId', 'title'));
    }
}
