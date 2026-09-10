<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\Cache\CacheVersionManager;
use Psr\Cache\CacheItemPoolInterface;

class CategoryObserver
{
    public function __construct(
        protected CacheItemPoolInterface $pool,
        protected CacheVersionManager $versionManager
    ) {}

    /**
     * Handle the Category "saved" event (created or updated).
     */
    public function saved(Category $category): void
    {
        $this->pool->deleteItem("category_{$category->id}");
        $this->versionManager->bumpVersion(CacheVersionManager::TAG_CATEGORIES);
        $this->versionManager->bumpVersion(CacheVersionManager::TAG_PRODUCTS);
    }

    /**
     * Handle the Category "deleted" event.
     */
    public function deleted(Category $category): void
    {
        $this->pool->deleteItem("category_{$category->id}");
        $this->versionManager->bumpVersion(CacheVersionManager::TAG_CATEGORIES);
        $this->versionManager->bumpVersion(CacheVersionManager::TAG_PRODUCTS);
    }
}
