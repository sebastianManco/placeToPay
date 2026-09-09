<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Rap2hpoutre\FastExcel\FastExcel;
use Tests\TestCase;

class AdminProductImportExportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to create an admin user.
     */
    protected function createAdmin(array $attributes = []): User
    {
        return User::create(array_merge([
            'identification' => 70000001,
            'name' => 'Admin',
            'last_Name' => 'Tester',
            'email' => 'admin_import_export@example.com',
            'phone' => '123456789',
            'direction' => 'HQ Street',
            'user_Name' => 'admin_impexp',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'admin',
        ], $attributes));
    }

    /**
     * Helper to create a client user.
     */
    protected function createClient(array $attributes = []): User
    {
        static $counter = 1;
        $num = 71000000 + ($counter++);

        return User::create(array_merge([
            'identification' => $num,
            'name' => "Client{$counter}",
            'last_Name' => 'Test',
            'email' => "client{$counter}@example.com",
            'phone' => '3009998888',
            'direction' => 'Street 200',
            'user_Name' => "client_user{$counter}",
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'client',
        ], $attributes));
    }

    /**
     * Helper to generate a temporary CSV file with rows.
     */
    protected function createCsvFile(array $rows, string $filename = 'test_products.csv'): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'csv_');
        (new FastExcel(collect($rows)))->export($tempPath);

        return new UploadedFile($tempPath, $filename, 'text/csv', null, true);
    }

    /**
     * Helper to generate a temporary XLSX file with rows.
     */
    protected function createXlsxFile(array $rows, string $filename = 'test_products.xlsx'): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'xlsx_') . '.xlsx';
        (new FastExcel(collect($rows)))->export($tempPath);

        return new UploadedFile($tempPath, $filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    // -----------------------------------------------------------------
    // Authorization Tests
    // -----------------------------------------------------------------

    public function test_guests_cannot_access_export_or_import(): void
    {
        $this->get('/admin/products/export')
            ->assertStatus(302)
            ->assertRedirect('/home/login');

        $this->post('/admin/products/import')
            ->assertStatus(302)
            ->assertRedirect('/home/login');
    }

    public function test_non_admin_client_cannot_access_export_or_import(): void
    {
        $client = $this->createClient();

        $this->actingAs($client)
            ->get('/admin/products/export')
            ->assertStatus(403);

        $this->actingAs($client)
            ->post('/admin/products/import')
            ->assertStatus(403);
    }

    // -----------------------------------------------------------------
    // Export Tests
    // -----------------------------------------------------------------

    public function test_admin_can_export_products_to_excel_xlsx(): void
    {
        $admin = $this->createAdmin();
        $category = Category::create(['name' => 'Lácteos', 'is_active' => true]);

        Product::create([
            'name' => 'Leche Entera',
            'category_id' => $category->id,
            'price' => 4500.00,
            'stock' => 100,
            'is_active' => true,
            'description' => 'Leche fresca pasteurizada',
        ]);

        $response = $this->actingAs($admin)->get('/admin/products/export');

        $response->assertStatus(200);
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));
    }

    public function test_admin_can_export_products_to_csv(): void
    {
        $admin = $this->createAdmin();

        Product::create([
            'name' => 'Pan Tajado',
            'price' => 3200.00,
            'stock' => 50,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get('/admin/products/export?format=csv');

        $response->assertStatus(200);
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.csv', (string) $response->headers->get('content-disposition'));
    }

    // -----------------------------------------------------------------
    // Import Tests (Creation & Update)
    // -----------------------------------------------------------------

    public function test_admin_can_import_new_products_from_csv(): void
    {
        $admin = $this->createAdmin();

        $file = $this->createCsvFile([
            [
                'id' => '',
                'name' => 'Arroz Diana 1kg',
                'category' => 'Granos',
                'price' => 4800,
                'stock' => 80,
                'is_active' => '1',
                'description' => 'Arroz blanco seleccionado',
            ],
            [
                'id' => '',
                'name' => 'Frijol Rojo 500g',
                'category' => 'Granos',
                'price' => 6200,
                'stock' => 45,
                'is_active' => '1',
                'description' => 'Frijol rojo cargamanto',
            ],
        ]);

        $response = $this->actingAs($admin)->post('/admin/products/import', [
            'file' => $file,
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/admin/products');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'name' => 'Arroz Diana 1kg',
            'price' => 4800,
            'stock' => 80,
        ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Frijol Rojo 500g',
            'price' => 6200,
            'stock' => 45,
        ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Granos',
        ]);
    }

    public function test_admin_can_import_new_products_from_xlsx(): void
    {
        $admin = $this->createAdmin();

        $file = $this->createXlsxFile([
            [
                'id' => '',
                'name' => 'Queso Campesino 500g',
                'category' => 'Lácteos',
                'price' => 9500,
                'stock' => 20,
                'is_active' => '1',
                'description' => 'Queso fresco artesanal',
            ],
        ]);

        $response = $this->actingAs($admin)->post('/admin/products/import', [
            'file' => $file,
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/admin/products');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'name' => 'Queso Campesino 500g',
            'price' => 9500,
            'stock' => 20,
        ]);
    }

    public function test_admin_can_update_existing_products_via_import(): void
    {
        $admin = $this->createAdmin();

        $product = Product::create([
            'name' => 'Café Tradicional 500g',
            'price' => 12000,
            'stock' => 15,
            'is_active' => true,
            'description' => 'Café molido original',
        ]);

        $file = $this->createCsvFile([
            [
                'id' => $product->id,
                'name' => 'Café Tradicional Premium 500g',
                'category' => 'Bebidas',
                'price' => 14500,
                'stock' => 35,
                'is_active' => '1',
                'description' => 'Café molido seleccionado',
            ],
        ]);

        $response = $this->actingAs($admin)->post('/admin/products/import', [
            'file' => $file,
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/admin/products');
        $response->assertSessionHas('success');

        $product->refresh();
        $this->assertSame('Café Tradicional Premium 500g', $product->name);
        $this->assertEquals(14500.00, (float) $product->price);
        $this->assertEquals(35, $product->stock);
        $this->assertSame('Café molido seleccionado', $product->description);
    }

    public function test_admin_can_mix_create_and_update_in_same_import(): void
    {
        $admin = $this->createAdmin();

        $existing = Product::create([
            'name' => 'Azúcar 1kg',
            'price' => 4000,
            'stock' => 20,
        ]);

        $file = $this->createCsvFile([
            [
                'id' => $existing->id,
                'name' => 'Azúcar Blanca 1kg',
                'price' => 4200,
                'stock' => 25,
            ],
            [
                'id' => '',
                'name' => 'Sal Refinada 1kg',
                'price' => 1800,
                'stock' => 60,
            ],
        ]);

        $response = $this->actingAs($admin)->post('/admin/products/import', [
            'file' => $file,
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/admin/products');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'id' => $existing->id,
            'name' => 'Azúcar Blanca 1kg',
            'price' => 4200,
        ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Sal Refinada 1kg',
            'price' => 1800,
        ]);
    }

    // -----------------------------------------------------------------
    // Validation & Error Handling Tests
    // -----------------------------------------------------------------

    public function test_import_requires_a_file(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/products/import', []);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['file']);
    }

    public function test_import_rejects_invalid_file_extension(): void
    {
        $admin = $this->createAdmin();
        $fakePdf = UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf');

        $response = $this->actingAs($admin)->post('/admin/products/import', [
            'file' => $fakePdf,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['file']);
    }

    public function test_import_handles_row_validation_errors(): void
    {
        $admin = $this->createAdmin();

        $file = $this->createCsvFile([
            [
                'id' => '',
                'name' => '', // Missing name
                'price' => -500, // Negative price
                'stock' => 'invalido', // Not an integer
            ],
            [
                'id' => 999999, // Non-existent product ID
                'name' => 'Producto Inexistente',
                'price' => 5000,
                'stock' => 10,
            ],
        ]);

        $response = $this->actingAs($admin)->post('/admin/products/import', [
            'file' => $file,
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/admin/products');
        $response->assertSessionHas('import_errors');

        $errors = session('import_errors');
        $this->assertCount(2, $errors);
    }

    // -----------------------------------------------------------------
    // UI Elements Test
    // -----------------------------------------------------------------

    public function test_products_index_displays_export_and_import_buttons(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin/products');

        $response->assertStatus(200);
        $response->assertSee('Exportar Excel');
        $response->assertSee('Importar Excel');
        $response->assertSee(route('admin.products.export'));
        $response->assertSee(route('admin.products.import'));
        $response->assertSee('importModal');
    }
}
