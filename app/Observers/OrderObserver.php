<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\Cache\CacheVersionManager;

class OrderObserver
{
    public function __construct(
        protected CacheVersionManager $versionManager
    ) {}

    /**
     * Handle the Order "saved" event (created or updated).
     */
    public function saved(Order $order): void
    {
        $this->versionManager->bumpVersion(CacheVersionManager::TAG_REPORTS);
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        $this->versionManager->bumpVersion(CacheVersionManager::TAG_REPORTS);
    }
}
