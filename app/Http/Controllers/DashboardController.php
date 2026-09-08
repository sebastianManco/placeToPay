<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the product showcase dashboard for registered clients.
     */
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $categoryId = $request->query('category');
        $title = $request->query('title', 'Vitrina de Productos');

        $query = Product::with('category')->where('is_active', true);

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($categoryId)) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->orderBy('name')->paginate(12)->withQueryString();
        $categories = Category::where('is_active', true)->orderBy('name')->get();

        return view('dashboard', compact('products', 'categories', 'search', 'categoryId', 'title'));
    }
}
