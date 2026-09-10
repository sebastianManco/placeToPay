<?php

namespace App\Traits;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasRolesAndPermissions
{
    /**
     * The roles that belong to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Direct permissions that belong to the user.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * Determine if the user has a specific role (or any of the provided roles).
     */
    public function hasRole(string|array|Role $roles): bool
    {
        $roleList = is_array($roles) ? $roles : [$roles];

        foreach ($roleList as $role) {
            $slug = $role instanceof Role ? $role->slug : $role;

            // Check legacy role column first for backward compatibility
            if (isset($this->attributes['role']) && $this->attributes['role'] === $slug) {
                return true;
            }

            // Check relationship roles
            if ($this->roles->contains(function ($item) use ($slug) {
                return $item->slug === $slug || $item->name === $slug;
            })) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the user has any of the given roles.
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->hasRole($roles);
    }

    /**
     * Determine if the user has all of the given roles.
     */
    public function hasAllRoles(array $roles): bool
    {
        foreach ($roles as $role) {
            if (! $this->hasRole($role)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the user has a specific permission.
     */
    public function hasPermission(string $permissionSlug): bool
    {
        // Superadmin bypass: admin role or legacy admin has all permissions
        if ($this->isAdmin()) {
            return true;
        }

        $slugs = $this->getCachedPermissionSlugs();

        return in_array($permissionSlug, $slugs, true);
    }

    /**
     * Get cached consolidated permission slugs for the user via PSR-6.
     *
     * @return array<string>
     */
    public function getCachedPermissionSlugs(): array
    {
        $userId = $this->identification ?? $this->id;
        if (! $userId) {
            return [];
        }

        try {
            /** @var \Psr\Cache\CacheItemPoolInterface $pool */
            $pool = app(\Psr\Cache\CacheItemPoolInterface::class);
            /** @var \App\Services\Cache\CacheVersionManager $versionManager */
            $versionManager = app(\App\Services\Cache\CacheVersionManager::class);

            $cacheKey = $versionManager->makeKey('roles', "user_perms_{$userId}");
            $item = $pool->getItem($cacheKey);

            if ($item->isHit()) {
                return (array) $item->get();
            }

            $directSlugs = $this->permissions->pluck('slug')->all();
            $roleSlugs = $this->roles->flatMap->permissions->pluck('slug')->all();
            $slugs = array_values(array_unique(array_merge($directSlugs, $roleSlugs)));

            $item->set($slugs);
            $item->expiresAfter(3600); // 1 hour
            $pool->save($item);

            return $slugs;
        } catch (\Throwable) {
            $directSlugs = $this->permissions->pluck('slug')->all();
            $roleSlugs = $this->roles->flatMap->permissions->pluck('slug')->all();
            return array_values(array_unique(array_merge($directSlugs, $roleSlugs)));
        }
    }

    /**
     * Invalidate user's permission cache.
     */
    public function invalidatePermissionsCache(): void
    {
        $userId = $this->identification ?? $this->id;
        if (! $userId) {
            return;
        }

        try {
            /** @var \Psr\Cache\CacheItemPoolInterface $pool */
            $pool = app(\Psr\Cache\CacheItemPoolInterface::class);
            /** @var \App\Services\Cache\CacheVersionManager $versionManager */
            $versionManager = app(\App\Services\Cache\CacheVersionManager::class);

            $cacheKey = $versionManager->makeKey('roles', "user_perms_{$userId}");
            $pool->deleteItem($cacheKey);
        } catch (\Throwable) {
            // Ignore if cache unavailable
        }
    }

    /**
     * Determine if the user has any of the given permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the user has all of the given permissions.
     */
    public function hasAllPermissions(array $permissions): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        foreach ($permissions as $permission) {
            if (! $this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the user has any administrative permission to access the admin area.
     */
    public function hasAnyAdminPermission(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        // Check if user has any assigned permission or non-client role
        if ($this->permissions->isNotEmpty()) {
            return true;
        }

        return $this->roles->contains(function ($role) {
            return $role->slug !== 'client';
        });
    }

    /**
     * Assign a role to the user.
     */
    public function assignRole(string|Role $role): self
    {
        $roleModel = is_string($role)
            ? Role::where('slug', $role)->orWhere('name', $role)->firstOrFail()
            : $role;

        $this->roles()->syncWithoutDetaching([$roleModel->id]);

        // Keep legacy role attribute in sync if not already set or if assigning admin
        if ($roleModel->slug === 'admin' && isset($this->attributes['role'])) {
            $this->attributes['role'] = 'admin';
            $this->saveQuietly();
        }

        // Reload relationship
        $this->unsetRelation('roles');
        $this->invalidatePermissionsCache();

        return $this;
    }

    /**
     * Remove a role from the user.
     */
    public function removeRole(string|Role $role): self
    {
        $roleId = is_string($role)
            ? Role::where('slug', $role)->orWhere('name', $role)->value('id')
            : $role->id;

        if ($roleId) {
            $this->roles()->detach($roleId);
        }

        $this->unsetRelation('roles');
        $this->invalidatePermissionsCache();

        return $this;
    }

    /**
     * Synchronize roles for the user.
     */
    public function syncRoles(array $roles): self
    {
        $ids = [];

        foreach ($roles as $role) {
            if ($role instanceof Role) {
                $ids[] = $role->id;
            } elseif (is_numeric($role)) {
                $ids[] = (int) $role;
            } elseif (is_string($role)) {
                $id = Role::where('slug', $role)->orWhere('name', $role)->value('id');
                if ($id) {
                    $ids[] = $id;
                }
            }
        }

        $this->roles()->sync($ids);
        $this->unsetRelation('roles');
        $this->invalidatePermissionsCache();

        return $this;
    }

    /**
     * Give a direct permission to the user.
     */
    public function givePermission(string|Permission $permission): self
    {
        $permissionModel = is_string($permission)
            ? Permission::where('slug', $permission)->firstOrFail()
            : $permission;

        $this->permissions()->syncWithoutDetaching([$permissionModel->id]);
        $this->unsetRelation('permissions');
        $this->invalidatePermissionsCache();

        return $this;
    }

    /**
     * Revoke a direct permission from the user.
     */
    public function revokePermission(string|Permission $permission): self
    {
        $permissionId = is_string($permission)
            ? Permission::where('slug', $permission)->value('id')
            : $permission->id;

        if ($permissionId) {
            $this->permissions()->detach($permissionId);
        }

        $this->unsetRelation('permissions');
        $this->invalidatePermissionsCache();

        return $this;
    }

    /**
     * Sync direct permissions for the user.
     */
    public function syncPermissions(array $permissions): self
    {
        $ids = [];

        foreach ($permissions as $permission) {
            if ($permission instanceof Permission) {
                $ids[] = $permission->id;
            } elseif (is_numeric($permission)) {
                $ids[] = (int) $permission;
            } elseif (is_string($permission)) {
                $id = Permission::where('slug', $permission)->value('id');
                if ($id) {
                    $ids[] = $id;
                }
            }
        }

        $this->permissions()->sync($ids);
        $this->unsetRelation('permissions');
        $this->invalidatePermissionsCache();

        return $this;
    }

    /**
     * Get all consolidated permissions (direct + role-based).
     */
    public function getAllPermissions(): Collection
    {
        $rolePermissions = $this->roles->flatMap->permissions;
        $directPermissions = $this->permissions;

        return $rolePermissions->merge($directPermissions)->unique('id');
    }
}
