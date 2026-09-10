<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductSearchRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\Cache\CacheVersionManager;
use Illuminate\View\View;
use Psr\Cache\CacheItemPoolInterface;

class DashboardController extends Controller
{
    public function __construct(
        protected CacheItemPoolInterface $cachePool,
        protected CacheVersionManager $versionManager
    ) {}

    /**
     * Display the product showcase dashboard for registered clients with custom search and filters.
     */
    public function index(ProductSearchRequest $request): View
    {
        $filters = $request->validated();
        $search = $request->query('search');
        $categoryId = $request->query('category');
        $title = $request->query('title', 'Vitrina de Productos');

        $productsKey = $this->versionManager->makeKey(
            CacheVersionManager::TAG_PRODUCTS,
            'showcase_' . md5(json_encode($request->query()))
        );
        $productsItem = $this->cachePool->getItem($productsKey);

        if ($productsItem->isHit()) {
            $products = $productsItem->get();
        } else {
            $products = Product::with('category')
                ->where('is_active', true)
                ->filter($filters)
                ->paginate(12)
                ->withQueryString();

            $productsItem->set($products);
            $productsItem->expiresAfter(900);
            $this->cachePool->save($productsItem);
        }

        $categoriesKey = $this->versionManager->makeKey(
            CacheVersionManager::TAG_CATEGORIES,
            'active_all'
        );
        $categoriesItem = $this->cachePool->getItem($categoriesKey);

        if ($categoriesItem->isHit()) {
            $categories = $categoriesItem->get();
        } else {
            $categories = Category::where('is_active', true)->orderBy('name')->get();

            $categoriesItem->set($categories);
            $categoriesItem->expiresAfter(3600);
            $this->cachePool->save($categoriesItem);
        }

        return view('dashboard', compact('products', 'categories', 'filters', 'search', 'categoryId', 'title'));
    }
}
