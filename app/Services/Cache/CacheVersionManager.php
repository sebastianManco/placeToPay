<?php

namespace App\Services\Cache;

use Psr\Cache\CacheItemPoolInterface;

class CacheVersionManager
{
    public const TAG_PRODUCTS = 'products';
    public const TAG_CATEGORIES = 'categories';
    public const TAG_REPORTS = 'reports';

    public function __construct(
        protected CacheItemPoolInterface $pool
    ) {}

    /**
     * Get the current version number for a tag.
     */
    public function getVersion(string $tag): int
    {
        $key = "tag_version_{$tag}";
        $item = $this->pool->getItem($key);

        if ($item->isHit()) {
            return (int) $item->get();
        }

        $item->set(1);
        $item->expiresAfter(86400 * 30); // 30 days
        $this->pool->save($item);

        return 1;
    }

    /**
     * Bump (increment) the version number for a tag to invalidate all associated cache entries.
     */
    public function bumpVersion(string $tag): int
    {
        $key = "tag_version_{$tag}";
        $current = $this->getVersion($tag);
        $newVersion = $current + 1;

        $item = $this->pool->getItem($key);
        $item->set($newVersion);
        $item->expiresAfter(86400 * 30);
        $this->pool->save($item);

        return $newVersion;
    }

    /**
     * Generate a versioned cache key compliant with PSR-6.
     */
    public function makeKey(string $tag, string $suffix = ''): string
    {
        $version = $this->getVersion($tag);
        $cleanSuffix = preg_replace('/[^a-zA-Z0-9_-]/', '_', $suffix);

        if ($cleanSuffix !== '') {
            return "{$tag}_v{$version}_{$cleanSuffix}";
        }

        return "{$tag}_v{$version}";
    }
}
