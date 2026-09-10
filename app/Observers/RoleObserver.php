<?php

namespace App\Observers;

use App\Models\Role;
use App\Services\Cache\CacheVersionManager;

class RoleObserver
{
    public function __construct(
        protected CacheVersionManager $versionManager
    ) {}

    /**
     * Handle the Role "saved" event.
     */
    public function saved(Role $role): void
    {
        $this->versionManager->bumpVersion('roles');
    }

    /**
     * Handle the Role "deleted" event.
     */
    public function deleted(Role $role): void
    {
        $this->versionManager->bumpVersion('roles');
    }
}
