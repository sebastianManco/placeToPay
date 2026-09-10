<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\GenerateReportJob;
use App\Models\Category;
use App\Models\Product;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to create an admin user.
     */
    protected function createAdmin(): User
    {
        $user = User::create([
            'identification' => 88000001,
            'name' => 'Report Admin',
            'last_name' => 'Tester',
            'email' => 'admin@reports.test',
            'phone' => '3001234567',
            'direction' => 'Main Ave',
            'user_name' => 'reportadmin',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $user->syncRoles(['admin']);

        return $user;
    }

    /**
     * Helper to create a regular client user without report permissions.
     */
    protected function createClient(): User
    {
        return User::create([
            'identification' => 88000002,
            'name' => 'Report Client',
            'last_name' => 'Tester',
            'email' => 'client@reports.test',
            'phone' => '3009990000',
            'direction' => 'Side Ave',
            'user_name' => 'reportclient',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'client',
        ]);
    }

    /**
     * Test unauthenticated access to reports returns 401 Unauthorized.
     */
    public function test_unauthenticated_access_to_reports_returns_401(): void
    {
        $responseIndex = $this->getJson(route('api.v1.reports.index'));
        $responseIndex->assertUnauthorized();

        $responseMetrics = $this->getJson(route('api.v1.reports.metrics.sales'));
        $responseMetrics->assertUnauthorized();
    }

    /**
     * Test user without permissions cannot access reports (403 Forbidden).
     */
    public function test_client_without_permission_cannot_access_reports(): void
    {
        $client = $this->createClient();
        Sanctum::actingAs($client, ['*']);

        $responseIndex = $this->getJson(route('api.v1.reports.index'));
        $responseIndex->assertForbidden();

        $responseMetrics = $this->getJson(route('api.v1.reports.metrics.sales'));
        $responseMetrics->assertForbidden();
    }

    /**
     * Test listing reports with pagination.
     */
    public function test_can_list_reports_with_pagination(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin, ['*']);

        Report::create([
            'title' => 'Reporte de Ventas Q1',
            'type' => Report::TYPE_SALES,
            'format' => Report::FORMAT_PDF,
            'status' => Report::STATUS_COMPLETED,
        ]);
        Report::create([
            'title' => 'Reporte de Inventario',
            'type' => Report::TYPE_INVENTORY_ALERTS,
            'format' => Report::FORMAT_XLSX,
            'status' => Report::STATUS_PENDING,
        ]);

        $response = $this->getJson(route('api.v1.reports.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'type',
                        'format',
                        'status',
                        'is_completed',
                        'parameters',
                        'downloads',
                        'created_at',
                    ],
                ],
                'meta',
                'links',
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    /**
     * Test filtering reports by type and status.
     */
    public function test_can_filter_reports_by_type_and_status(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin, ['*']);

        Report::create([
            'title' => 'Reporte Ventas',
            'type' => Report::TYPE_SALES,
            'format' => Report::FORMAT_PDF,
            'status' => Report::STATUS_COMPLETED,
        ]);
        Report::create([
            'title' => 'Reporte Inventario',
            'type' => Report::TYPE_INVENTORY_ALERTS,
            'format' => Report::FORMAT_XLSX,
            'status' => Report::STATUS_PENDING,
        ]);

        $response = $this->getJson(route('api.v1.reports.index', [
            'type' => Report::TYPE_SALES,
            'status' => Report::STATUS_COMPLETED,
        ]));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Reporte Ventas', $response->json('data.0.title'));
    }

    /**
     * Test enqueuing a new report returns 202 Accepted and dispatches background job.
     */
    public function test_can_enqueue_new_report(): void
    {
        Queue::fake();

        $admin = $this->createAdmin();
        Sanctum::actingAs($admin, ['*']);

        $payload = [
            'title' => 'Reporte Completo Semestral',
            'type' => Report::TYPE_COMPLETE,
            'format' => Report::FORMAT_BOTH,
            'date_from' => '2026-01-01',
            'date_to' => '2026-06-30',
            'low_stock_threshold' => 10,
            'days_inactive' => 45,
        ];

        $response = $this->postJson(route('api.v1.reports.store'), $payload);

        $response->assertStatus(202)
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'Reporte Completo Semestral',
                    'type' => Report::TYPE_COMPLETE,
                    'format' => Report::FORMAT_BOTH,
                    'status' => Report::STATUS_PENDING,
                    'is_pending' => true,
                ],
            ]);

        $this->assertDatabaseHas('reports', [
            'title' => 'Reporte Completo Semestral',
            'status' => Report::STATUS_PENDING,
        ]);

        Queue::assertPushed(GenerateReportJob::class);
    }

    /**
     * Test enqueuing validation error returns 422.
     */
    public function test_cannot_enqueue_report_with_invalid_parameters(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin, ['*']);

        $response = $this->postJson(route('api.v1.reports.store'), [
            'title' => '',
            'type' => 'invalid_type',
            'format' => 'invalid_format',
            'date_from' => 'not-a-date',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Los datos enviados no son válidos.',
            ])
            ->assertJsonValidationErrors(['title', 'type', 'format', 'date_from']);
    }

    /**
     * Test viewing report details.
     */
    public function test_can_show_report(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin, ['*']);

        $report = Report::create([
            'title' => 'Auditoría de Pagos',
            'type' => Report::TYPE_PAYMENTS,
            'format' => Report::FORMAT_PDF,
            'status' => Report::STATUS_COMPLETED,
            'summary_data' => ['total_transactions' => 150],
        ]);

        $response = $this->getJson(route('api.v1.reports.show', $report));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $report->id,
                    'title' => 'Auditoría de Pagos',
                    'type' => Report::TYPE_PAYMENTS,
                    'is_completed' => true,
                    'summary_data' => [
                        'total_transactions' => 150,
                    ],
                ],
            ]);
    }

    /**
     * Test deleting a report returns 204 No Content.
     */
    public function test_can_delete_report(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin, ['*']);

        $report = Report::create([
            'title' => 'Reporte para borrar',
            'type' => Report::TYPE_ORDERS,
            'format' => Report::FORMAT_PDF,
            'status' => Report::STATUS_COMPLETED,
        ]);

        $response = $this->deleteJson(route('api.v1.reports.destroy', $report));

        $response->assertNoContent();
        $this->assertDatabaseMissing('reports', ['id' => $report->id]);
    }

    /**
     * Test downloading report returns 404 when file does not exist yet.
     */
    public function test_download_returns_404_when_file_not_ready(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin, ['*']);

        $report = Report::create([
            'title' => 'Reporte Sin Archivo',
            'type' => Report::TYPE_SALES,
            'format' => Report::FORMAT_PDF,
            'status' => Report::STATUS_PENDING,
            'file_path' => null,
        ]);

        $response = $this->getJson(route('api.v1.reports.download', ['report' => $report, 'format' => 'pdf']));

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test downloading report file when generated.
     */
    public function test_can_download_generated_report_file(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin, ['*']);

        Storage::fake('local');

        $fakeFilePath = 'reports/test_report.pdf';
        Storage::disk('local')->put($fakeFilePath, '%PDF-1.4 simulated content');

        $report = Report::create([
            'title' => 'Reporte Listo',
            'type' => Report::TYPE_SALES,
            'format' => Report::FORMAT_PDF,
            'status' => Report::STATUS_COMPLETED,
            'file_path' => $fakeFilePath,
        ]);

        $response = $this->get(route('api.v1.reports.download', ['report' => $report, 'format' => 'pdf']));

        $response->assertOk();
    }

    /**
     * Test real-time live sales metrics endpoint.
     */
    public function test_can_get_live_sales_metrics(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson(route('api.v1.reports.metrics.sales'));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'total_sales',
                    'approved_orders_count',
                    'total_orders_count',
                    'average_ticket',
                    'conversion_rate',
                    'daily_sales',
                ],
            ]);
    }

    /**
     * Test real-time live payment metrics endpoint.
     */
    public function test_can_get_live_payment_metrics(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson(route('api.v1.reports.metrics.payments'));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'total_transactions',
                    'total_amount',
                    'statuses',
                ],
            ]);
    }

    /**
     * Test real-time live top products metrics endpoint.
     */
    public function test_can_get_live_top_products_metrics(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson(route('api.v1.reports.metrics.top-products'));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ]);
    }

    /**
     * Test real-time live inventory alerts metrics endpoint.
     */
    public function test_can_get_live_inventory_alerts_metrics(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin, ['*']);

        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id, 'stock' => 0]);
        Product::factory()->create(['category_id' => $category->id, 'stock' => 2]);

        $response = $this->getJson(route('api.v1.reports.metrics.inventory-alerts'));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'threshold',
                    'days_inactive',
                    'out_of_stock',
                    'out_of_stock_count',
                    'low_stock',
                    'low_stock_count',
                    'dead_stock',
                    'dead_stock_count',
                ],
            ]);

        $this->assertEquals(1, $response->json('data.out_of_stock_count'));
        $this->assertEquals(1, $response->json('data.low_stock_count'));
    }
}
