<?php

namespace App\Contracts;

use App\Models\Report;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

interface ReportServiceInterface
{
    /**
     * Compute sales overview metrics (total revenue, count, average ticket, daily breakdown).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getSalesReport(array $filters = []): array;

    /**
     * Get a query builder for orders filtered by date range and status.
     *
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getOrdersQuery(array $filters = []): Builder;

    /**
     * Get the top-selling products by quantity sold and revenue generated.
     *
     * @param  array<string, mixed>  $filters
     * @param  int  $limit
     * @return \Illuminate\Support\Collection
     */
    public function getTopSellingProducts(array $filters = [], int $limit = 10): Collection;

    /**
     * Compute PlaceToPay payment gateway metrics and statuses breakdown.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getPaymentStatusesReport(array $filters = []): array;

    /**
     * Compute inventory alerts: low stock, out of stock, and dead/low rotation products.
     *
     * @param  int  $lowStockThreshold
     * @param  int  $daysInactive
     * @return array<string, mixed>
     */
    public function getInventoryAlerts(int $lowStockThreshold = 5, int $daysInactive = 30): array;

    /**
     * Compile consolidated report data based on report configuration.
     *
     * @param  \App\Models\Report  $report
     * @return array<string, mixed>
     */
    public function compileReportData(Report $report): array;

    /**
     * Generate and store a PDF document for the report.
     *
     * @param  \App\Models\Report  $report
     * @param  array<string, mixed>  $data
     * @return string Relative storage path of generated PDF
     */
    public function generatePdf(Report $report, array $data): string;

    /**
     * Generate and store an Excel document for the report.
     *
     * @param  \App\Models\Report  $report
     * @param  array<string, mixed>  $data
     * @return string Relative storage path of generated Excel
     */
    public function generateExcel(Report $report, array $data): string;

    /**
     * Process report generation asynchronously, creating requested export files and updating status.
     *
     * @param  \App\Models\Report  $report
     * @return void
     */
    public function process(Report $report): void;
}
