<?php

namespace App\Services\Cache;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;

class Psr6CacheItem implements CacheItemInterface
{
    protected ?DateTimeInterface $expiration = null;

    public function __construct(
        protected string $key,
        protected mixed $value = null,
        protected bool $isHit = false
    ) {}

    /**
     * {@inheritdoc}
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * {@inheritdoc}
     */
    public function get(): mixed
    {
        return $this->isHit() ? $this->value : null;
    }

    /**
     * {@inheritdoc}
     */
    public function isHit(): bool
    {
        if (! $this->isHit) {
            return false;
        }

        if ($this->expiration !== null && $this->expiration->getTimestamp() <= time()) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->isHit = true;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function expiresAt(?DateTimeInterface $expiration): static
    {
        $this->expiration = $expiration;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function expiresAfter(int|DateInterval|null $time): static
    {
        if ($time === null) {
            $this->expiration = null;
        } elseif ($time instanceof DateInterval) {
            $this->expiration = (new DateTimeImmutable())->add($time);
        } else {
            $this->expiration = (new DateTimeImmutable())->modify("+{$time} seconds");
        }

        return $this;
    }

    /**
     * Get the expiration date time.
     */
    public function getExpiration(): ?DateTimeInterface
    {
        return $this->expiration;
    }

    /**
     * Calculate remaining TTL in seconds.
     */
    public function getTtl(): ?int
    {
        if ($this->expiration === null) {
            return null;
        }

        $seconds = $this->expiration->getTimestamp() - time();

        return $seconds > 0 ? $seconds : 0;
    }
}
