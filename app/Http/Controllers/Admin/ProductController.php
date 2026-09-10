<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\ProductSpreadsheetServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportProductRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Jobs\ImportProductsJob;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImport;
use Illuminate\Http\JsonResponse;
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
        $latestImport = ProductImport::orderByDesc('id')->first();

        return view('admin.products.index', compact('products', 'search', 'status', 'categories', 'categoryId', 'latestImport'));
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
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $data['is_active'] = $request->boolean('is_active', true);

        Product::create($data);

        return redirect()->route('admin.products.index')
            ->with('success', 'Producto creado exitosamente.');
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
        $data = $request->validated();

        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($product->image && Storage::disk('public')->exists($product->image)) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $data['is_active'] = $request->boolean('is_active', true);

        $product->update($data);

        return redirect()->route('admin.products.index')
            ->with('success', 'Producto actualizado exitosamente.');
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Product $product): RedirectResponse
    {
        if ($product->image && Storage::disk('public')->exists($product->image)) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return redirect()->route('admin.products.index')
            ->with('success', 'Producto eliminado exitosamente.');
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
     * Export products to a spreadsheet (.xlsx or .csv).
     */
    public function export(Request $request): Response
    {
        $format = strtolower($request->query('format', 'xlsx'));
        if (! in_array($format, ['xlsx', 'csv'], true)) {
            $format = 'xlsx';
        }

        return $this->spreadsheetService->export($format);
    }

    /**
     * Import products from uploaded spreadsheet asynchronously via queue.
     */
    public function import(ImportProductRequest $request): RedirectResponse
    {
        $file = $request->file('file');

        // Store file temporarily in local storage
        $storedPath = $file->store('imports', 'local');

        $productImport = ProductImport::create([
            'user_identification' => $request->user()?->identification,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $storedPath,
            'status' => ProductImport::STATUS_PENDING,
        ]);

        // Dispatch asynchronous import job to queue
        ImportProductsJob::dispatch($productImport);

        // Check if job completed immediately (sync queue driver used in testing)
        $productImport->refresh();

        if ($productImport->isCompleted() || $productImport->isFailed()) {
            if ($productImport->hasErrors() || $productImport->isFailed()) {
                if ($productImport->created_count > 0 || $productImport->updated_count > 0) {
                    return redirect()->route('admin.products.index')
                        ->with('warning', $productImport->getSummaryMessage())
                        ->with('import_errors', $productImport->errors ?? []);
                }

                return redirect()->route('admin.products.index')
                    ->with('error', $productImport->getSummaryMessage())
                    ->with('import_errors', $productImport->errors ?? []);
            }

            return redirect()->route('admin.products.index')
                ->with('success', $productImport->getSummaryMessage());
        }

        return redirect()->route('admin.products.index')
            ->with('success', "El archivo '{$productImport->file_name}' ha sido encolado para su importación en segundo plano (ID #{$productImport->id}).");
    }

    /**
     * Check status of a product import job via JSON for real-time polling.
     */
    public function importStatus(ProductImport $import): JsonResponse
    {
        return response()->json([
            'id' => $import->id,
            'file_name' => $import->file_name,
            'status' => $import->status,
            'is_completed' => $import->isCompleted(),
            'is_processing' => $import->isProcessing(),
            'is_failed' => $import->isFailed(),
            'total_rows' => $import->total_rows,
            'processed_rows' => $import->processed_rows,
            'created_count' => $import->created_count,
            'updated_count' => $import->updated_count,
            'failed_count' => $import->failed_count,
            'errors' => $import->errors ?? [],
            'error_message' => $import->error_message,
            'started_at' => $import->started_at?->toIso8601String(),
            'completed_at' => $import->completed_at?->toIso8601String(),
        ]);
    }
}
