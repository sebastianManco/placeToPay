<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\ProductSpreadsheetServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportProductRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected ProductSpreadsheetServiceInterface $spreadsheetService
    ) {}

    /**
     * Display a listing of products.
     */
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $categoryId = $request->query('category');

        $query = Product::with('category');

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if (! empty($categoryId)) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->orderBy('name')->paginate(10)->withQueryString();
        $categories = Category::where('is_active', true)->orderBy('name')->get();

        return view('admin.products.index', compact('products', 'search', 'status', 'categories', 'categoryId'));
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(): View
    {
        $categories = Category::where('is_active', true)->orderBy('name')->get();

        return view('admin.products.create', compact('categories'));
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($validated);

        return redirect()->route('admin.products.index')
            ->with('success', "El producto \"{$product->name}\" ha sido creado correctamente.");
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product): View
    {
        $categories = Category::where('is_active', true)->orderBy('name')->get();

        return view('admin.products.edit', compact('product', 'categories'));
    }

    /**
     * Update the specified product in storage.
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('image')) {
            // Delete the old image if it exists
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        if ($request->has('is_active')) {
            $validated['is_active'] = (bool) $request->input('is_active');
        }

        $product->update($validated);

        return redirect()->route('admin.products.index')
            ->with('success', "El producto \"{$product->name}\" ha sido actualizado correctamente.");
    }

    /**
     * Toggle product active status.
     */
    public function toggleStatus(Product $product): RedirectResponse
    {
        $product->is_active = ! $product->is_active;
        $product->save();

        $statusText = $product->is_active ? 'habilitado' : 'inhabilitado';

        return redirect()->back()->with('success', "El producto \"{$product->name}\" ha sido {$statusText} correctamente.");
    }

    /**
     * Export products to spreadsheet (.xlsx or .csv).
     */
    public function export(Request $request): Response
    {
        $format = $request->query('format', 'xlsx');
        if (! in_array($format, ['xlsx', 'csv'], true)) {
            $format = 'xlsx';
        }

        return $this->spreadsheetService->export($format);
    }

    /**
     * Import products from uploaded spreadsheet.
     */
    public function import(ImportProductRequest $request): RedirectResponse
    {
        $file = $request->file('file');
        $result = $this->spreadsheetService->import($file);

        if ($result->hasErrors()) {
            if ($result->hasSuccess()) {
                return redirect()->route('admin.products.index')
                    ->with('warning', $result->getSummaryMessage())
                    ->with('import_errors', $result->errors);
            }

            return redirect()->route('admin.products.index')
                ->with('error', $result->getSummaryMessage())
                ->with('import_errors', $result->errors);
        }

        return redirect()->route('admin.products.index')
            ->with('success', $result->getSummaryMessage());
    }
}
