<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\ProductSpreadsheetServiceInterface;
use App\Http\Requests\ImportProductRequest;
use App\Http\Requests\Api\V1\StoreProductApiRequest;
use App\Http\Requests\Api\V1\UpdateProductApiRequest;
use App\Http\Requests\Api\V1\UpdateProductStockApiRequest;
use App\Http\Resources\V1\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

use App\Services\Cache\CacheVersionManager;
use Psr\Cache\CacheItemPoolInterface;

class ProductApiController extends BaseApiController
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected ProductSpreadsheetServiceInterface $spreadsheetService,
        protected CacheItemPoolInterface $cachePool,
        protected CacheVersionManager $versionManager
    ) {}

    /**
     * Display a paginated listing of products with filtering, search and sorting.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        $cacheKey = $this->versionManager->makeKey(
            CacheVersionManager::TAG_PRODUCTS,
            'index_' . md5(json_encode($request->query()))
        );

        $item = $this->cachePool->getItem($cacheKey);

        if ($item->isHit()) {
            $products = $item->get();
        } else {
            $query = Product::with('category')->filter($request->query());
            $products = $query->paginate($perPage);

            $item->set($products);
            $item->expiresAfter(900); // 15 minutes
            $this->cachePool->save($item);
        }

        return $this->paginatedResponse(
            $products,
            ProductResource::class,
            'Listado de productos obtenido exitosamente.'
        );
    }

    /**
     * Store a newly created product.
     *
     * @param  \App\Http\Requests\Api\V1\StoreProductApiRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreProductApiRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! isset($data['is_active'])) {
            $data['is_active'] = true;
        }

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($data);
        $product->load('category');

        $location = route('api.v1.products.show', ['product' => $product->id]);

        return $this->createdResponse(
            new ProductResource($product),
            'Producto creado exitosamente.',
            $location
        );
    }

    /**
     * Display the specified product.
     *
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Product $product): JsonResponse
    {
        $cacheKey = "product_{$product->id}";
        $item = $this->cachePool->getItem($cacheKey);

        if ($item->isHit()) {
            $productData = $item->get();
            return $this->successResponse(
                new ProductResource($productData),
                'Producto obtenido exitosamente.'
            );
        }

        $product->load('category');
        $item->set($product);
        $item->expiresAfter(3600); // 1 hour
        $this->cachePool->save($item);

        return $this->successResponse(
            new ProductResource($product),
            'Producto obtenido exitosamente.'
        );
    }

    /**
     * Update the specified product (supports full PUT and partial PATCH).
     *
     * @param  \App\Http\Requests\Api\V1\UpdateProductApiRequest  $request
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateProductApiRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($product->image && Storage::disk('public')->exists($product->image)) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);
        $product->load('category');

        return $this->successResponse(
            new ProductResource($product),
            'Producto actualizado exitosamente.'
        );
    }

    /**
     * Partially update the stock of a product.
     *
     * @param  \App\Http\Requests\Api\V1\UpdateProductStockApiRequest  $request
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStock(UpdateProductStockApiRequest $request, Product $product): JsonResponse
    {
        $validated = $request->validated();

        if (array_key_exists('stock', $validated) && $validated['stock'] !== null) {
            $product->stock = (int) $validated['stock'];
        } elseif (array_key_exists('adjustment', $validated) && $validated['adjustment'] !== null) {
            $newStock = (int) $product->stock + (int) $validated['adjustment'];
            if ($newStock < 0) {
                return $this->errorResponse(
                    "El ajuste de {$validated['adjustment']} unidades dejaría el inventario en negativo ({$newStock}).",
                    HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
                    ['adjustment' => ['El ajuste resultante no puede ser menor a 0.']]
                );
            }
            $product->stock = $newStock;
        }

        $product->save();
        $product->load('category');

        return $this->successResponse(
            new ProductResource($product),
            'Stock del producto actualizado exitosamente.'
        );
    }

    /**
     * Remove the specified product from storage.
     *
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\Response
     */
    public function destroy(Product $product): Response
    {
        if ($product->image && Storage::disk('public')->exists($product->image)) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return $this->noContentResponse();
    }

    /**
     * Export products to a spreadsheet (.xlsx or .csv).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function export(Request $request): HttpResponse
    {
        $format = strtolower($request->query('format', 'xlsx'));
        if (! in_array($format, ['xlsx', 'csv'], true)) {
            $format = 'xlsx';
        }

        return $this->spreadsheetService->export($format);
    }

    /**
     * Import products from an uploaded spreadsheet file (.xlsx or .csv).
     *
     * @param  \App\Http\Requests\ImportProductRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function import(ImportProductRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $result = $this->spreadsheetService->import($file);

        $payload = [
            'created' => $result->created,
            'updated' => $result->updated,
            'total_processed' => $result->created + $result->updated,
            'errors' => $result->errors,
        ];

        if ($result->hasErrors()) {
            $status = $result->hasSuccess() ? HttpResponse::HTTP_OK : HttpResponse::HTTP_UNPROCESSABLE_ENTITY;
            return response()->json([
                'success' => $result->hasSuccess(),
                'message' => $result->getSummaryMessage(),
                'data' => $payload,
            ], $status);
        }

        return $this->successResponse(
            $payload,
            $result->getSummaryMessage()
        );
    }
}
