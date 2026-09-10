<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\Cache\CacheVersionManager;
use Psr\Cache\CacheItemPoolInterface;

class ProductObserver
{
    public function __construct(
        protected CacheItemPoolInterface $pool,
        protected CacheVersionManager $versionManager
    ) {}

    /**
     * Handle the Product "saved" event (created or updated).
     */
    public function saved(Product $product): void
    {
        $this->pool->deleteItem("product_{$product->id}");
        $this->versionManager->bumpVersion(CacheVersionManager::TAG_PRODUCTS);
        $this->versionManager->bumpVersion(CacheVersionManager::TAG_REPORTS);
        $this->versionManager->bumpVersion(CacheVersionManager::TAG_CATEGORIES);
    }

    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $product): void
    {
        $this->pool->deleteItem("product_{$product->id}");
        $this->versionManager->bumpVersion(CacheVersionManager::TAG_PRODUCTS);
        $this->versionManager->bumpVersion(CacheVersionManager::TAG_REPORTS);
        $this->versionManager->bumpVersion(CacheVersionManager::TAG_CATEGORIES);
    }
}
