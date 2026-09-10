<?php

namespace App\Services\Cache;

use App\Exceptions\Cache\CacheInvalidArgumentException;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class Psr6CachePool implements CacheItemPoolInterface
{
    /**
     * @var array<string, CacheItemInterface>
     */
    protected array $deferred = [];

    public function __construct(
        protected CacheRepository $repository
    ) {}

    /**
     * Ensure all deferred items are committed when the pool is destructed.
     */
    public function __destruct()
    {
        $this->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function getItem(string $key): CacheItemInterface
    {
        $this->validateKey($key);

        if (isset($this->deferred[$key])) {
            $deferred = $this->deferred[$key];
            if ($deferred->isHit()) {
                return clone $deferred;
            }
            unset($this->deferred[$key]);
        }

        if ($this->repository->has($key)) {
            $value = $this->repository->get($key);
            return new Psr6CacheItem($key, $value, true);
        }

        return new Psr6CacheItem($key, null, false);
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = []): iterable
    {
        if (empty($keys)) {
            return [];
        }

        $items = [];
        foreach ($keys as $key) {
            $this->validateKey($key);
            $items[$key] = $this->getItem($key);
        }

        return $items;
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem(string $key): bool
    {
        $this->validateKey($key);

        if (isset($this->deferred[$key])) {
            return $this->deferred[$key]->isHit();
        }

        return $this->repository->has($key);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $this->deferred = [];

        try {
            return (bool) $this->repository->flush();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem(string $key): bool
    {
        $this->validateKey($key);

        unset($this->deferred[$key]);

        return (bool) $this->repository->forget($key);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys): bool
    {
        $allSuccess = true;

        foreach ($keys as $key) {
            $this->validateKey($key);
            unset($this->deferred[$key]);
            if (! $this->repository->forget($key)) {
                $allSuccess = false;
            }
        }

        return $allSuccess;
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item): bool
    {
        $this->validateKey($item->getKey());

        unset($this->deferred[$item->getKey()]);

        if (! $item->isHit()) {
            // If item has an expired lifetime, delete it
            $this->repository->forget($item->getKey());
            return true;
        }

        $ttl = null;
        if ($item instanceof Psr6CacheItem) {
            $expiration = $item->getExpiration();
            if ($expiration !== null) {
                if ($expiration->getTimestamp() <= time()) {
                    $this->repository->forget($item->getKey());
                    return true;
                }
                $ttl = $expiration;
            }
        }

        try {
            if ($ttl !== null) {
                return (bool) $this->repository->put($item->getKey(), $item->get(), $ttl);
            }

            return (bool) $this->repository->forever($item->getKey(), $item->get());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function saveDeferred(CacheItemInterface $item): bool
    {
        $this->validateKey($item->getKey());

        $this->deferred[$item->getKey()] = $item;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): bool
    {
        $success = true;

        foreach ($this->deferred as $item) {
            if (! $this->save($item)) {
                $success = false;
            }
        }

        $this->deferred = [];

        return $success;
    }

    /**
     * Validate key according to PSR-6 specifications.
     *
     * @throws CacheInvalidArgumentException
     */
    public function validateKey(mixed $key): void
    {
        if (! is_string($key) || $key === '') {
            throw new CacheInvalidArgumentException('Cache key must be a non-empty string.');
        }

        if (preg_match('/[{}()\/\\\\@:]/', $key)) {
            throw new CacheInvalidArgumentException(
                sprintf('Cache key "%s" contains reserved characters {}()/\@:', $key)
            );
        }
    }
}
