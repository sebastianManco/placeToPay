<?php

namespace Tests\Feature\Cache;

use App\Contracts\ReportServiceInterface;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Cache\CacheVersionManager;
use App\Services\Cache\Psr6CachePool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Psr\Cache\CacheItemPoolInterface;
use Tests\TestCase;

class Psr6ApplicationCacheTest extends TestCase
{
    use RefreshDatabase;

    protected CacheItemPoolInterface $pool;
    protected CacheVersionManager $versionManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pool = $this->app->make(CacheItemPoolInterface::class);
        $this->versionManager = $this->app->make(CacheVersionManager::class);
    }

    public function test_container_resolves_psr6_pool_as_singleton(): void
    {
        $instance1 = $this->app->make(CacheItemPoolInterface::class);
        $instance2 = $this->app->make(CacheItemPoolInterface::class);

        $this->assertInstanceOf(Psr6CachePool::class, $instance1);
        $this->assertSame($instance1, $instance2);
    }

    public function test_category_api_index_is_cached_and_invalidated_on_model_events(): void
    {
        Category::create([
            'name' => 'Electrónica',
            'description' => 'Gadgets',
            'is_active' => true,
        ]);

        $initialVersion = $this->versionManager->getVersion(CacheVersionManager::TAG_CATEGORIES);

        // First request: caches categories
        $response1 = $this->getJson(route('api.v1.categories.index'));
        $response1->assertOk();
        $this->assertCount(1, $response1->json('data'));

        // Check cache item exists
        $cacheKey = $this->versionManager->makeKey(
            CacheVersionManager::TAG_CATEGORIES,
            'index_' . md5(json_encode([]))
        );
        $this->assertTrue($this->pool->hasItem($cacheKey));

        // Creating a second category must bump version and invalidate previous listing cache
        $newCategory = Category::create([
            'name' => 'Hogar',
            'description' => 'Muebles',
            'is_active' => true,
        ]);

        $newVersion = $this->versionManager->getVersion(CacheVersionManager::TAG_CATEGORIES);
        $this->assertGreaterThan($initialVersion, $newVersion);

        // Second request should now return both categories
        $response2 = $this->getJson(route('api.v1.categories.index'));
        $response2->assertOk();
        $this->assertCount(2, $response2->json('data'));
    }

    public function test_category_show_caches_and_invalidates_on_update(): void
    {
        $category = Category::create([
            'name' => 'Juguetes',
            'description' => 'Para niños',
            'is_active' => true,
        ]);

        // First call: cache miss, then populated
        $response1 = $this->getJson(route('api.v1.categories.show', ['category' => $category->id]));
        $response1->assertOk();
        $this->assertEquals('Juguetes', $response1->json('data.name'));

        // Cache item exists
        $this->assertTrue($this->pool->hasItem("category_{$category->id}"));

        // Update category
        $category->update(['name' => 'Juguetes y Juegos']);

        // Observer should have removed category_{id}
        $this->assertFalse($this->pool->hasItem("category_{$category->id}"));

        // Second call should return updated name
        $response2 = $this->getJson(route('api.v1.categories.show', ['category' => $category->id]));
        $response2->assertOk();
        $this->assertEquals('Juguetes y Juegos', $response2->json('data.name'));
    }

    public function test_product_api_show_is_cached_and_invalidated_on_stock_adjustment(): void
    {
        $category = Category::create(['name' => 'Ropa', 'is_active' => true]);

        $product = Product::create([
            'name' => 'Camisa Oxford',
            'price' => 45000,
            'stock' => 15,
            'is_active' => true,
            'category_id' => $category->id,
        ]);

        // First request populates product_{id}
        $response1 = $this->getJson(route('api.v1.products.show', ['product' => $product->id]));
        $response1->assertOk();
        $this->assertEquals(15, $response1->json('data.stock'));
        $this->assertTrue($this->pool->hasItem("product_{$product->id}"));

        // Adjust stock via API (protected route requiring Sanctum & permissions)
        $admin = User::create([
            'identification' => 88000001,
            'name' => 'Admin',
            'last_name' => 'Cache',
            'email' => 'admin.cache@test.com',
            'phone' => '3001234567',
            'direction' => 'Calle 100',
            'user_name' => 'admincache',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $admin->syncRoles(['admin']);
        Sanctum::actingAs($admin, ['*']);

        $responseStock = $this->patchJson(route('api.v1.products.stock', ['product' => $product->id]), [
            'stock' => 25,
        ]);
        $responseStock->assertOk();

        // Product cache should be reloaded or invalidated
        $response2 = $this->getJson(route('api.v1.products.show', ['product' => $product->id]));
        $response2->assertOk();
        $this->assertEquals(25, $response2->json('data.stock'));
    }

    public function test_report_service_metrics_are_cached_via_psr6(): void
    {
        /** @var ReportServiceInterface $reportService */
        $reportService = $this->app->make(ReportServiceInterface::class);

        $initialReport = $reportService->getSalesReport();
        $this->assertIsArray($initialReport);

        $cacheKey = $this->versionManager->makeKey(
            CacheVersionManager::TAG_REPORTS,
            'sales_' . md5(json_encode([]))
        );

        $this->assertTrue($this->pool->hasItem($cacheKey));

        // Bumping reports version invalidates
        $this->versionManager->bumpVersion(CacheVersionManager::TAG_REPORTS);
        $newKey = $this->versionManager->makeKey(
            CacheVersionManager::TAG_REPORTS,
            'sales_' . md5(json_encode([]))
        );
        $this->assertNotEquals($cacheKey, $newKey);
    }

    public function test_user_permission_slugs_are_cached_and_invalidated_on_role_changes(): void
    {
        $role = Role::create([
            'name' => 'Gestor de Productos',
            'slug' => 'product_manager',
        ]);

        $permission = Permission::create([
            'name' => 'Ver Productos',
            'slug' => 'products.view',
            'module' => 'products',
        ]);

        $role->givePermission($permission);

        $user = User::factory()->create([
            'identification' => '1098765432',
            'name' => 'Carlos Gerente',
            'email' => 'carlos@example.com',
        ]);

        $user->assignRole($role);

        // First check: should be true and cached
        $this->assertTrue($user->hasPermission('products.view'));

        $cacheKey = $this->versionManager->makeKey('roles', "user_perms_{$user->identification}");
        $this->assertTrue($this->pool->hasItem($cacheKey));

        // Remove role
        $user->removeRole($role);

        // Cache item must be cleared
        $this->assertFalse($this->pool->hasItem($cacheKey));

        // Now hasPermission should return false
        $this->assertFalse($user->hasPermission('products.view'));
    }
}
