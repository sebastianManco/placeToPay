<?php

namespace Tests\Unit\Cache;

use App\Exceptions\Cache\CacheInvalidArgumentException;
use App\Services\Cache\Psr6CacheItem;
use App\Services\Cache\Psr6CachePool;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException as PsrInvalidArgumentException;

class Psr6CacheTest extends TestCase
{
    protected Psr6CachePool $pool;
    protected Repository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new Repository(new ArrayStore());
        $this->pool = new Psr6CachePool($this->repository);
    }

    public function test_pool_implements_psr6_interface(): void
    {
        $this->assertInstanceOf(CacheItemPoolInterface::class, $this->pool);
    }

    public function test_item_implements_psr6_interface(): void
    {
        $item = new Psr6CacheItem('test_key');
        $this->assertInstanceOf(CacheItemInterface::class, $item);
    }

    public function test_get_item_miss_returns_unhit_item(): void
    {
        $item = $this->pool->getItem('missing_key');

        $this->assertEquals('missing_key', $item->getKey());
        $this->assertFalse($item->isHit());
        $this->assertNull($item->get());
    }

    public function test_save_and_get_item_hit(): void
    {
        $item = $this->pool->getItem('user_42');
        $item->set(['name' => 'Alice', 'role' => 'admin']);
        $saved = $this->pool->save($item);

        $this->assertTrue($saved);
        $this->assertTrue($this->pool->hasItem('user_42'));

        $fetched = $this->pool->getItem('user_42');
        $this->assertTrue($fetched->isHit());
        $this->assertEquals(['name' => 'Alice', 'role' => 'admin'], $fetched->get());
    }

    public function test_has_item(): void
    {
        $this->assertFalse($this->pool->hasItem('some_key'));

        $item = $this->pool->getItem('some_key');
        $item->set('some_value');
        $this->pool->save($item);

        $this->assertTrue($this->pool->hasItem('some_key'));
    }

    public function test_delete_item(): void
    {
        $item = $this->pool->getItem('key_to_delete');
        $item->set('value');
        $this->pool->save($item);

        $this->assertTrue($this->pool->hasItem('key_to_delete'));

        $deleted = $this->pool->deleteItem('key_to_delete');
        $this->assertTrue($deleted);
        $this->assertFalse($this->pool->hasItem('key_to_delete'));

        $fetched = $this->pool->getItem('key_to_delete');
        $this->assertFalse($fetched->isHit());
    }

    public function test_get_items(): void
    {
        $keys = ['k1', 'k2', 'k3'];

        $item1 = $this->pool->getItem('k1')->set('v1');
        $item2 = $this->pool->getItem('k2')->set('v2');
        $this->pool->save($item1);
        $this->pool->save($item2);

        $items = $this->pool->getItems($keys);

        $this->assertCount(3, $items);
        $this->assertTrue($items['k1']->isHit());
        $this->assertEquals('v1', $items['k1']->get());
        $this->assertTrue($items['k2']->isHit());
        $this->assertEquals('v2', $items['k2']->get());
        $this->assertFalse($items['k3']->isHit());
        $this->assertNull($items['k3']->get());
    }

    public function test_delete_items(): void
    {
        $this->pool->save($this->pool->getItem('del1')->set('v1'));
        $this->pool->save($this->pool->getItem('del2')->set('v2'));

        $this->assertTrue($this->pool->hasItem('del1'));
        $this->assertTrue($this->pool->hasItem('del2'));

        $this->pool->deleteItems(['del1', 'del2']);

        $this->assertFalse($this->pool->hasItem('del1'));
        $this->assertFalse($this->pool->hasItem('del2'));
    }

    public function test_clear(): void
    {
        $this->pool->save($this->pool->getItem('c1')->set('v1'));
        $this->pool->save($this->pool->getItem('c2')->set('v2'));

        $this->assertTrue($this->pool->clear());

        $this->assertFalse($this->pool->hasItem('c1'));
        $this->assertFalse($this->pool->hasItem('c2'));
    }

    public function test_save_deferred_and_commit(): void
    {
        $item = $this->pool->getItem('deferred_key')->set('deferred_value');
        $this->pool->saveDeferred($item);

        // Within the pool, deferred item is immediately accessible
        $pending = $this->pool->getItem('deferred_key');
        $this->assertTrue($pending->isHit());
        $this->assertEquals('deferred_value', $pending->get());

        // But not in underlying repository before commit
        $this->assertFalse($this->repository->has('deferred_key'));

        $committed = $this->pool->commit();
        $this->assertTrue($committed);

        // Now committed in repository
        $this->assertTrue($this->repository->has('deferred_key'));
        $this->assertEquals('deferred_value', $this->repository->get('deferred_key'));
    }

    public function test_item_expiration_with_expires_after_seconds(): void
    {
        $item = $this->pool->getItem('expiring_sec');
        $item->set('quick_val');
        $item->expiresAfter(3600);

        $this->pool->save($item);
        $this->assertTrue($this->pool->hasItem('expiring_sec'));

        // Past expiration
        $expiredItem = $this->pool->getItem('expired_already');
        $expiredItem->set('val');
        $expiredItem->expiresAfter(-10);

        $this->assertFalse($expiredItem->isHit());
        $this->assertNull($expiredItem->get());

        $this->pool->save($expiredItem);
        $this->assertFalse($this->pool->hasItem('expired_already'));
    }

    public function test_item_expiration_with_expires_at(): void
    {
        $future = (new DateTimeImmutable())->modify('+2 hours');
        $item = $this->pool->getItem('future_key')->set('val')->expiresAt($future);

        $this->assertTrue($item->isHit());
        $this->pool->save($item);
        $this->assertTrue($this->pool->hasItem('future_key'));

        $past = (new DateTimeImmutable())->modify('-2 hours');
        $expired = $this->pool->getItem('past_key')->set('val')->expiresAt($past);

        $this->assertFalse($expired->isHit());
        $this->assertNull($expired->get());
    }

    public function test_item_expiration_with_date_interval(): void
    {
        $interval = new DateInterval('PT1H'); // 1 hour
        $item = $this->pool->getItem('interval_key')->set('val')->expiresAfter($interval);

        $this->assertTrue($item->isHit());
        $this->assertNotNull($item->getExpiration());
    }

    public function test_invalid_key_characters_throw_psr_invalid_argument_exception(): void
    {
        $invalidKeys = [
            '',
            'key{with}brace',
            'key(with)paren',
            'key/with/slash',
            'key\\with\\backslash',
            'key@with@at',
            'key:with:colon',
        ];

        foreach ($invalidKeys as $invalidKey) {
            try {
                $this->pool->getItem($invalidKey);
                $this->fail("Expected Psr\\Cache\\InvalidArgumentException for key: '{$invalidKey}'");
            } catch (\Throwable $e) {
                $this->assertInstanceOf(
                    PsrInvalidArgumentException::class,
                    $e,
                    "Exception must implement Psr\\Cache\\InvalidArgumentException for key '{$invalidKey}'"
                );
            }
        }
    }
}
