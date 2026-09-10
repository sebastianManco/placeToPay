<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreCategoryApiRequest;
use App\Http\Requests\Api\V1\UpdateCategoryApiRequest;
use App\Http\Resources\V1\CategoryResource;
use App\Http\Resources\V1\ProductResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

use App\Services\Cache\CacheVersionManager;
use Psr\Cache\CacheItemPoolInterface;

class CategoryApiController extends BaseApiController
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected CacheItemPoolInterface $cachePool,
        protected CacheVersionManager $versionManager
    ) {}

    /**
     * Display a paginated listing of categories.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $isActive = $request->query('is_active');
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        $cacheKey = $this->versionManager->makeKey(
            CacheVersionManager::TAG_CATEGORIES,
            'index_' . md5(json_encode($request->query()))
        );

        $item = $this->cachePool->getItem($cacheKey);

        if ($item->isHit()) {
            $categories = $item->get();
        } else {
            $query = Category::query()->withCount('products');

            if (! empty($search)) {
                $query->where('name', 'like', "%{$search}%");
            }

            if ($isActive !== null && $isActive !== '') {
                $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
            }

            $categories = $query->orderBy('name')->paginate($perPage);

            $item->set($categories);
            $item->expiresAfter(1800); // 30 minutes
            $this->cachePool->save($item);
        }

        return $this->paginatedResponse(
            $categories,
            CategoryResource::class,
            'Listado de categorías obtenido exitosamente.'
        );
    }

    /**
     * Store a newly created category.
     *
     * @param  \App\Http\Requests\Api\V1\StoreCategoryApiRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreCategoryApiRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (! isset($data['is_active'])) {
            $data['is_active'] = true;
        }

        $category = Category::create($data);
        $category->loadCount('products');

        $location = route('api.v1.categories.show', ['category' => $category->id]);

        return $this->createdResponse(
            new CategoryResource($category),
            'Categoría creada exitosamente.',
            $location
        );
    }

    /**
     * Display the specified category.
     *
     * @param  \App\Models\Category  $category
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Category $category): JsonResponse
    {
        $cacheKey = "category_{$category->id}";
        $item = $this->cachePool->getItem($cacheKey);

        if ($item->isHit()) {
            $categoryData = $item->get();
            return $this->successResponse(
                new CategoryResource($categoryData),
                'Categoría obtenida exitosamente.'
            );
        }

        $category->loadCount('products');
        $item->set($category);
        $item->expiresAfter(3600); // 1 hour
        $this->cachePool->save($item);

        return $this->successResponse(
            new CategoryResource($category),
            'Categoría obtenida exitosamente.'
        );
    }

    /**
     * Update the specified category (supports PUT and PATCH).
     *
     * @param  \App\Http\Requests\Api\V1\UpdateCategoryApiRequest  $request
     * @param  \App\Models\Category  $category
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateCategoryApiRequest $request, Category $category): JsonResponse
    {
        $category->update($request->validated());
        $category->loadCount('products');

        return $this->successResponse(
            new CategoryResource($category),
            'Categoría actualizada exitosamente.'
        );
    }

    /**
     * Remove the specified category from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Category  $category
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Category $category): Response|JsonResponse
    {
        $productsCount = $category->products()->count();

        if ($productsCount > 0) {
            $force = filter_var($request->query('force', false), FILTER_VALIDATE_BOOLEAN);

            if (! $force) {
                return $this->errorResponse(
                    "No se puede eliminar la categoría porque contiene {$productsCount} productos asociados. Reasigne los productos o incluya el parámetro ?force=true para desvincularlos automáticamente.",
                    HttpResponse::HTTP_CONFLICT
                );
            }

            // Unlink associated products
            $category->products()->update(['category_id' => null]);
        }

        $category->delete();

        return $this->noContentResponse();
    }

    /**
     * List products belonging to the specified category (Hierarchical sub-resource).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Category  $category
     * @return \Illuminate\Http\JsonResponse
     */
    public function products(Request $request, Category $category): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        $cacheKey = $this->versionManager->makeKey(
            CacheVersionManager::TAG_CATEGORIES,
            "cat_{$category->id}_products_" . md5(json_encode($request->query()))
        );

        $item = $this->cachePool->getItem($cacheKey);

        if ($item->isHit()) {
            $products = $item->get();
        } else {
            $products = $category->products()
                ->with('category')
                ->filter($request->query())
                ->paginate($perPage);

            $item->set($products);
            $item->expiresAfter(900); // 15 minutes
            $this->cachePool->save($item);
        }

        return $this->paginatedResponse(
            $products,
            ProductResource::class,
            "Productos de la categoría \"{$category->name}\" obtenidos exitosamente."
        );
    }
}
