<?php

namespace Tests\Feature;

use App\Jobs\GenerateReportJob;
use App\Models\Order;
use App\Models\Product;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(): User
    {
        $user = User::create([
            'identification' => 80000001,
            'name' => 'Admin',
            'last_name' => 'Reporter',
            'email' => 'admin_reporter@test.com',
            'phone' => '123456789',
            'direction' => 'HQ Avenue',
            'user_name' => 'admin_rep',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $user->syncRoles(['admin']);

        return $user;
    }

    protected function createClient(): User
    {
        return User::create([
            'identification' => 81000001,
            'name' => 'Cliente',
            'last_name' => 'Normal',
            'email' => 'cliente_normal@test.com',
            'phone' => '987654321',
            'direction' => 'Street 10',
            'user_name' => 'client_rep',
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'client',
        ]);
    }

    public function test_guest_cannot_access_reports(): void
    {
        $response = $this->get(route('admin.reports.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_regular_client_cannot_access_admin_reports(): void
    {
        $client = $this->createClient();

        $response = $this->actingAs($client)->get(route('admin.reports.index'));
        $response->assertForbidden();
    }

    public function test_admin_can_view_reports_dashboard(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('admin.reports.index'));
        $response->assertOk();
        $response->assertViewIs('admin.reports.index');
        $response->assertViewHas(['reports', 'salesMetrics', 'paymentMetrics', 'topProducts', 'inventoryAlerts']);
    }

    public function test_admin_can_request_report_generation_which_dispatches_queued_job(): void
    {
        Queue::fake();
        $admin = $this->createAdmin();

        $payload = [
            'title' => 'Reporte Mensual Septiembre',
            'type' => Report::TYPE_COMPLETE,
            'format' => Report::FORMAT_BOTH,
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
            'low_stock_threshold' => 5,
        ];

        $response = $this->actingAs($admin)->post(route('admin.reports.store'), $payload);

        $response->assertRedirect(route('admin.reports.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('reports', [
            'title' => 'Reporte Mensual Septiembre',
            'type' => Report::TYPE_COMPLETE,
            'format' => Report::FORMAT_BOTH,
            'status' => Report::STATUS_PENDING,
            'user_identification' => $admin->identification,
        ]);

        $report = Report::first();

        Queue::assertPushed(GenerateReportJob::class, function (GenerateReportJob $job) use ($report) {
            return $job->report->id === $report->id;
        });
    }

    public function test_queued_job_executes_and_completes_report(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();

        $report = Report::create([
            'user_identification' => $admin->identification,
            'title' => 'Reporte Ventas Q3',
            'type' => Report::TYPE_SALES,
            'format' => Report::FORMAT_PDF,
            'status' => Report::STATUS_PENDING,
            'parameters' => [
                'date_from' => '2026-07-01',
                'date_to' => '2026-09-30',
            ],
        ]);

        // Run job synchronously
        $job = new GenerateReportJob($report);
        $job->handle(app(\App\Contracts\ReportServiceInterface::class));

        $report->refresh();

        $this->assertTrue($report->isCompleted());
        $this->assertNotNull($report->completed_at);
        $this->assertNotNull($report->summary_data);
        $this->assertNotNull($report->file_path);
        Storage::disk('local')->assertExists($report->file_path);
    }

    public function test_report_status_endpoint_returns_json(): void
    {
        $admin = $this->createAdmin();

        $report = Report::create([
            'user_identification' => $admin->identification,
            'title' => 'Test Status',
            'type' => Report::TYPE_ORDERS,
            'format' => Report::FORMAT_XLSX,
            'status' => Report::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.reports.status', $report));

        $response->assertOk();
        $response->assertJson([
            'id' => $report->id,
            'status' => Report::STATUS_COMPLETED,
            'is_completed' => true,
        ]);
    }

    public function test_admin_can_download_generated_report_files(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();

        $pdfPath = 'reports/test_report.pdf';
        $xlsxPath = 'reports/test_report.xlsx';
        Storage::disk('local')->put($pdfPath, 'PDF dummy content');
        Storage::disk('local')->put($xlsxPath, 'Excel dummy content');

        $report = Report::create([
            'user_identification' => $admin->identification,
            'title' => 'Download Test',
            'type' => Report::TYPE_COMPLETE,
            'format' => Report::FORMAT_BOTH,
            'status' => Report::STATUS_COMPLETED,
            'file_path' => $pdfPath,
            'excel_file_path' => $xlsxPath,
            'completed_at' => now(),
        ]);

        // Download PDF
        $pdfResponse = $this->actingAs($admin)->get(route('admin.reports.download', ['report' => $report, 'format' => 'pdf']));
        $pdfResponse->assertOk();
        $pdfResponse->assertHeader('content-disposition');

        // Download Excel
        $excelResponse = $this->actingAs($admin)->get(route('admin.reports.download', ['report' => $report, 'format' => 'xlsx']));
        $excelResponse->assertOk();
        $excelResponse->assertHeader('content-disposition');
    }
}
