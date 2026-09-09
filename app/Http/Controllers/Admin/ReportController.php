<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\ReportServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Jobs\GenerateReportJob;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Display the reports dashboard with interactive charts and generated report history.
     */
    public function index(Request $request, ReportServiceInterface $reportService): View
    {
        $filters = [
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        $reports = Report::with('user')
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        $salesMetrics = $reportService->getSalesReport($filters);
        $paymentMetrics = $reportService->getPaymentStatusesReport($filters);
        $topProducts = $reportService->getTopSellingProducts($filters, 5);
        $inventoryAlerts = $reportService->getInventoryAlerts(5, 30);

        return view('admin.reports.index', compact(
            'reports',
            'salesMetrics',
            'paymentMetrics',
            'topProducts',
            'inventoryAlerts',
            'filters'
        ));
    }

    /**
     * Enqueue a new report generation job.
     */
    public function store(StoreReportRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $parameters = [
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'low_stock_threshold' => $validated['low_stock_threshold'] ?? 5,
            'days_inactive' => $validated['days_inactive'] ?? 30,
        ];

        $report = Report::create([
            'user_identification' => $request->user()->identification,
            'title' => $validated['title'],
            'type' => $validated['type'],
            'format' => $validated['format'],
            'status' => Report::STATUS_PENDING,
            'parameters' => $parameters,
        ]);

        // Dispatch queued background job
        GenerateReportJob::dispatch($report);

        return redirect()->route('admin.reports.index')
            ->with('success', "El reporte '{$report->title}' ha sido encolado para su generación en segundo plano.");
    }

    /**
     * Display details of a specific report.
     */
    public function show(Report $report): View
    {
        return view('admin.reports.show', compact('report'));
    }

    /**
     * Check report status via JSON for real-time frontend polling.
     */
    public function status(Report $report): JsonResponse
    {
        return response()->json([
            'id' => $report->id,
            'status' => $report->status,
            'is_completed' => $report->isCompleted(),
            'is_failed' => $report->isFailed(),
            'has_pdf' => $report->hasPdf(),
            'has_excel' => $report->hasExcel(),
            'download_pdf_url' => $report->hasPdf() ? route('admin.reports.download', ['report' => $report, 'format' => 'pdf']) : null,
            'download_excel_url' => $report->hasExcel() ? route('admin.reports.download', ['report' => $report, 'format' => 'xlsx']) : null,
            'summary_data' => $report->summary_data,
            'error_message' => $report->error_message,
        ]);
    }

    /**
     * Download a generated report file (PDF or Excel).
     */
    public function download(Report $report, string $format = 'pdf'): StreamedResponse|RedirectResponse
    {
        $normalizedFormat = strtolower($format);

        $path = match ($normalizedFormat) {
            'xlsx', 'excel' => $report->excel_file_path,
            default => $report->file_path,
        };

        if (empty($path) || ! Storage::disk('local')->exists($path)) {
            return redirect()->back()->with('error', 'El archivo solicitado aún no está listo o no existe en el servidor.');
        }

        $extension = $normalizedFormat === 'xlsx' || $normalizedFormat === 'excel' ? 'xlsx' : 'pdf';
        $downloadFilename = "reporte_{$report->id}_{$report->type}.{$extension}";

        return Storage::disk('local')->download($path, $downloadFilename);
    }
}