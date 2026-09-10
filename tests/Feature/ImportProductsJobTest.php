<?php

namespace Tests\Feature;

use App\Jobs\ImportProductsJob;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;
use Tests\TestCase;

class ImportProductsJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * Helper to create an admin user.
     */
    protected function createAdmin(array $attributes = []): User
    {
        $role = $attributes['role'] ?? 'admin';
        unset($attributes['role']);

        $user = User::create(array_merge([
            'identification' => 70000002,
            'name' => 'Admin Job Tester',
            'last_name' => 'Admin',
            'email' => 'admin_job@example.com',
            'phone' => '123456789',
            'direction' => 'HQ Ave',
            'user_name' => 'admin_job_user',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
        ], $attributes));

        $user->syncRoles([$role]);

        return $user;
    }

    /**
     * Helper to create an uploaded CSV file.
     */
    protected function createCsvFile(array $rows, string $filename = 'test_products.csv'): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'csv_');
        (new FastExcel(collect($rows)))->export($tempPath);

        return new UploadedFile($tempPath, $filename, 'text/csv', null, true);
    }

    /**
     * Test HTTP request saves file to local storage, enqueues ImportProductsJob and responds immediately.
     */
    public function test_admin_upload_enqueues_import_job_with_queue_fake(): void
    {
        Queue::fake();

        $admin = $this->createAdmin();
        $file = $this->createCsvFile([
            [
                'id' => '',
                'name' => 'Galletas Oreo',
                'category' => 'Snacks',
                'price' => 2500,
                'stock' => 50,
                'is_active' => '1',
                'description' => 'Galletas de chocolate con crema',
            ],
        ], 'galletas.csv');

        $response = $this->actingAs($admin)->post('/admin/products/import', [
            'file' => $file,
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/admin/products');
        $response->assertSessionHas('success');

        // Check product_imports database record
        $this->assertDatabaseHas('product_imports', [
            'file_name' => 'galletas.csv',
            'status' => ProductImport::STATUS_PENDING,
            'user_identification' => $admin->identification,
        ]);

        $import = ProductImport::first();
        $this->assertNotNull($import);
        $this->assertTrue(Storage::disk('local')->exists($import->file_path));

        // Verify ImportProductsJob was dispatched on 'imports' queue
        Queue::assertPushedOn('imports', ImportProductsJob::class, function (ImportProductsJob $job) use ($import) {
            return $job->productImport->id === $import->id;
        });
    }

    /**
     * Test job processes products in batches, precaches categories and updates DB.
     */
    public function test_job_processes_valid_products_in_batches_and_resolves_categories(): void
    {
        $admin = $this->createAdmin();
        $existingCat = Category::create(['name' => 'Bebidas', 'is_active' => true]);

        $existingProduct = Product::create([
            'name' => 'Jugo de Naranja 1L',
            'category_id' => $existingCat->id,
            'price' => 6000,
            'stock' => 10,
            'is_active' => true,
        ]);

        $fileContent = [
            // Update existing product
            [
                'id' => $existingProduct->id,
                'name' => 'Jugo de Naranja Premium 1L',
                'category' => 'Bebidas',
                'price' => 7500,
                'stock' => 25,
                'is_active' => '1',
                'description' => '100% natural',
            ],
            // Create product with existing category
            [
                'id' => '',
                'name' => 'Té Verde 500ml',
                'category' => 'Bebidas',
                'price' => 3800,
                'stock' => 40,
                'is_active' => '1',
                'description' => 'Té verde antioxidante',
            ],
            // Create product with new category
            [
                'id' => '',
                'name' => 'Detergente Polvo 1kg',
                'category' => 'Aseo y Limpieza',
                'price' => 8900,
                'stock' => 30,
                'is_active' => '1',
                'description' => 'Limpieza profunda',
            ],
        ];

        // Store file in local disk
        $tempPath = tempnam(sys_get_temp_dir(), 'csv_');
        (new FastExcel(collect($fileContent)))->export($tempPath);
        $storedPath = Storage::disk('local')->putFile('imports', new UploadedFile($tempPath, 'batch.csv', null, null, true));

        $import = ProductImport::create([
            'user_identification' => $admin->identification,
            'file_name' => 'batch.csv',
            'file_path' => $storedPath,
            'status' => ProductImport::STATUS_PENDING,
        ]);

        // Run job synchronously
        app()->make(\App\Contracts\ProductSpreadsheetServiceInterface::class);
        $job = new ImportProductsJob($import);
        app()->call([$job, 'handle']);

        $import->refresh();
        $this->assertSame(ProductImport::STATUS_COMPLETED, $import->status);
        $this->assertEquals(2, $import->created_count);
        $this->assertEquals(1, $import->updated_count);
        $this->assertEquals(0, $import->failed_count);
        $this->assertEquals(3, $import->total_rows);

        // Verify updated product in database
        $existingProduct->refresh();
        $this->assertSame('Jugo de Naranja Premium 1L', $existingProduct->name);
        $this->assertEquals(7500.00, (float) $existingProduct->price);
        $this->assertEquals(25, $existingProduct->stock);

        // Verify created products
        $this->assertDatabaseHas('products', [
            'name' => 'Té Verde 500ml',
            'category_id' => $existingCat->id,
            'stock' => 40,
        ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Aseo y Limpieza',
        ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Detergente Polvo 1kg',
            'stock' => 30,
        ]);
    }

    /**
     * Test job handles validation errors per row and records them in product_imports.
     */
    public function test_job_records_row_validation_errors_in_product_import(): void
    {
        $admin = $this->createAdmin();

        $rows = [
            // Valid row
            [
                'id' => '',
                'name' => 'Aceite Vegetal 900ml',
                'category' => 'Despensa',
                'price' => 9500,
                'stock' => 15,
                'is_active' => '1',
            ],
            // Missing name & negative price
            [
                'id' => '',
                'name' => '',
                'category' => 'Despensa',
                'price' => -100,
                'stock' => 10,
            ],
            // Non-existent ID
            [
                'id' => 9876543,
                'name' => 'Fantasma',
                'category' => 'Despensa',
                'price' => 1000,
                'stock' => 5,
            ],
        ];

        $tempPath = tempnam(sys_get_temp_dir(), 'csv_');
        (new FastExcel(collect($rows)))->export($tempPath);
        $storedPath = Storage::disk('local')->putFile('imports', new UploadedFile($tempPath, 'errors.csv', null, null, true));

        $import = ProductImport::create([
            'user_identification' => $admin->identification,
            'file_name' => 'errors.csv',
            'file_path' => $storedPath,
            'status' => ProductImport::STATUS_PENDING,
        ]);

        $job = new ImportProductsJob($import);
        app()->call([$job, 'handle']);

        $import->refresh();
        $this->assertSame(ProductImport::STATUS_COMPLETED, $import->status);
        $this->assertEquals(1, $import->created_count);
        $this->assertEquals(0, $import->updated_count);
        $this->assertEquals(2, $import->failed_count);
        $this->assertIsArray($import->errors);
        $this->assertCount(2, $import->errors);

        // Row 3 error: name & price validation
        $this->assertSame(3, $import->errors[0]['row']);
        $this->assertArrayHasKey('name', $import->errors[0]['errors']);

        // Row 4 error: non-existent ID
        $this->assertSame(4, $import->errors[1]['row']);
        $this->assertArrayHasKey('id', $import->errors[1]['errors']);

        // Only valid product was inserted
        $this->assertDatabaseHas('products', ['name' => 'Aceite Vegetal 900ml']);
        $this->assertDatabaseMissing('products', ['name' => 'Fantasma']);
    }

    /**
     * Test verification of resulting inventory after import.
     */
    public function test_job_verifies_resulting_inventory(): void
    {
        $admin = $this->createAdmin();

        $prod1 = Product::create([
            'name' => 'Atún en Agua 170g',
            'price' => 5200,
            'stock' => 12,
            'is_active' => true,
        ]);

        $rows = [
            [
                'id' => $prod1->id,
                'name' => 'Atún en Agua 170g',
                'price' => 5200,
                'stock' => 120, // New stock
                'is_active' => '1',
            ],
            [
                'id' => '',
                'name' => 'Sardinas en Tomate 150g',
                'price' => 4100,
                'stock' => 65, // Initial stock
                'is_active' => '1',
            ],
        ];

        $tempPath = tempnam(sys_get_temp_dir(), 'csv_');
        (new FastExcel(collect($rows)))->export($tempPath);
        $storedPath = Storage::disk('local')->putFile('imports', new UploadedFile($tempPath, 'inventory.csv', null, null, true));

        $import = ProductImport::create([
            'user_identification' => $admin->identification,
            'file_name' => 'inventory.csv',
            'file_path' => $storedPath,
            'status' => ProductImport::STATUS_PENDING,
        ]);

        $job = new ImportProductsJob($import);
        app()->call([$job, 'handle']);

        // Verify resulting stock in database
        $prod1->refresh();
        $this->assertSame(120, $prod1->stock);

        $prod2 = Product::where('name', 'Sardinas en Tomate 150g')->first();
        $this->assertNotNull($prod2);
        $this->assertSame(65, $prod2->stock);
    }

    /**
     * Test job handles missing file failure gracefully.
     */
    public function test_job_handles_missing_file_failure(): void
    {
        $admin = $this->createAdmin();

        $import = ProductImport::create([
            'user_identification' => $admin->identification,
            'file_name' => 'no_existe.xlsx',
            'file_path' => 'imports/non_existent_file.xlsx',
            'status' => ProductImport::STATUS_PENDING,
        ]);

        $job = new ImportProductsJob($import);
        app()->call([$job, 'handle']);

        $import->refresh();
        $this->assertSame(ProductImport::STATUS_FAILED, $import->status);
        $this->assertStringContainsString('no existe', (string) $import->error_message);
    }

    /**
     * Test import status endpoint returns JSON with progress and statistics.
     */
    public function test_import_status_endpoint_returns_json_statistics(): void
    {
        $admin = $this->createAdmin();

        $import = ProductImport::create([
            'user_identification' => $admin->identification,
            'file_name' => 'reporte.csv',
            'file_path' => 'imports/reporte.csv',
            'status' => ProductImport::STATUS_COMPLETED,
            'total_rows' => 50,
            'processed_rows' => 50,
            'created_count' => 45,
            'updated_count' => 3,
            'failed_count' => 2,
            'errors' => [
                ['row' => 10, 'errors' => ['price' => ['Precio inválido']], 'data' => []],
            ],
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get("/admin/products/imports/{$import->id}/status");

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $import->id,
            'file_name' => 'reporte.csv',
            'status' => 'completed',
            'is_completed' => true,
            'total_rows' => 50,
            'created_count' => 45,
            'updated_count' => 3,
            'failed_count' => 2,
        ]);
        $this->assertCount(1, $response->json('errors'));
    }
}
