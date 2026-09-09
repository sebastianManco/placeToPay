<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\ReportServiceInterface;
use App\Http\Requests\Api\V1\StoreReportApiRequest;
use App\Http\Resources\V1\ReportResource;
use App\Jobs\GenerateReportJob;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportApiController extends BaseApiController
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected ReportServiceInterface $reportService
    ) {}

    /**
     * Display a listing of generated reports.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $type = $request->query('type');
        $status = $request->query('status');

        $query = Report::query()->orderByDesc('created_at');

        if (! empty($type)) {
            $query->where('type', $type);
        }

        if (! empty($status)) {
            $query->where('status', $status);
        }

        $reports = $query->paginate($perPage);

        return $this->paginatedResponse(
            $reports,
            ReportResource::class,
            'Historial de reportes obtenido exitosamente.'
        );
    }

    /**
     * Enqueue a new report generation job (Asynchronous, HTTP 202 Accepted).
     *
     * @param  \App\Http\Requests\Api\V1\StoreReportApiRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreReportApiRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $parameters = [
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'low_stock_threshold' => (int) ($validated['low_stock_threshold'] ?? 5),
            'days_inactive' => (int) ($validated['days_inactive'] ?? 30),
        ];

        $userId = $request->user()?->identification;

        $report = Report::create([
            'user_identification' => $userId,
            'title' => $validated['title'],
            'type' => $validated['type'],
            'format' => $validated['format'],
            'status' => Report::STATUS_PENDING,
            'parameters' => $parameters,
        ]);

        // Dispatch asynchronous background queue job
        GenerateReportJob::dispatch($report);

        return $this->acceptedResponse(
            new ReportResource($report),
            "El reporte \"{$report->title}\" ha sido encolado para su generación en segundo plano."
        );
    }

    /**
     * Display details and execution status of a specific report.
     *
     * @param  \App\Models\Report  $report
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Report $report): JsonResponse
    {
        return $this->successResponse(
            new ReportResource($report),
            'Detalle del reporte obtenido exitosamente.'
        );
    }

    /**
     * Download a generated report file (PDF or Excel).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Report  $report
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\JsonResponse
     */
    public function download(Request $request, Report $report): StreamedResponse|JsonResponse
    {
        $format = strtolower($request->query('format', ''));
        if (empty($format)) {
            $format = $report->format === Report::FORMAT_XLSX ? 'xlsx' : 'pdf';
        }

        $path = match ($format) {
            'xlsx', 'excel' => $report->excel_file_path,
            default => $report->file_path,
        };

        if (empty($path) || ! Storage::disk('local')->exists($path)) {
            return $this->errorResponse(
                'El archivo solicitado aún no está listo o no existe en el almacenamiento del servidor.',
                Response::HTTP_NOT_FOUND
            );
        }

        $extension = in_array($format, ['xlsx', 'excel'], true) ? 'xlsx' : 'pdf';
        $downloadFilename = "reporte_{$report->id}_{$report->type}.{$extension}";

        return Storage::disk('local')->download($path, $downloadFilename);
    }

    /**
     * Delete a report and its stored file artifacts.
     *
     * @param  \App\Models\Report  $report
     * @return \Illuminate\Http\Response
     */
    public function destroy(Report $report): Response
    {
        if ($report->file_path && Storage::disk('local')->exists($report->file_path)) {
            Storage::disk('local')->delete($report->file_path);
        }

        if ($report->excel_file_path && Storage::disk('local')->exists($report->excel_file_path)) {
            Storage::disk('local')->delete($report->excel_file_path);
        }

        $report->delete();

        return $this->noContentResponse();
    }

    /**
     * Real-time live sales metrics.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function salesMetrics(Request $request): JsonResponse
    {
        $filters = [
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];

        $metrics = $this->reportService->getSalesReport($filters);

        return $this->successResponse(
            $metrics,
            'Métricas de ventas obtenidas en tiempo real.'
        );
    }

    /**
     * Real-time live payment statuses metrics.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function paymentMetrics(Request $request): JsonResponse
    {
        $filters = [
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];

        $metrics = $this->reportService->getPaymentStatusesReport($filters);

        return $this->successResponse(
            $metrics,
            'Métricas de estados de pago obtenidas en tiempo real.'
        );
    }

    /**
     * Real-time live ranking of top selling products.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function topProductsMetrics(Request $request): JsonResponse
    {
        $filters = [
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];
        $limit = min(max((int) $request->query('limit', 10), 1), 100);

        $topProducts = $this->reportService->getTopSellingProducts($filters, $limit);

        return $this->successResponse(
            $topProducts,
            'Ranking de productos más vendidos obtenido en tiempo real.'
        );
    }

    /**
     * Real-time live inventory alerts (out of stock, low stock, dead stock).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function inventoryAlertsMetrics(Request $request): JsonResponse
    {
        $lowThreshold = (int) $request->query('low_stock_threshold', 5);
        $daysInactive = (int) $request->query('days_inactive', 30);

        $alerts = $this->reportService->getInventoryAlerts($lowThreshold, $daysInactive);

        return $this->successResponse(
            $alerts,
            'Alertas de inventario y rotación obtenidas en tiempo real.'
        );
    }
}
