<?php

namespace App\Services\Report;

use App\Contracts\ReportServiceInterface;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Report;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;

use App\Services\Cache\CacheVersionManager;
use Psr\Cache\CacheItemPoolInterface;

class ReportService implements ReportServiceInterface
{
    public function __construct(
        protected ?CacheItemPoolInterface $cachePool = null,
        protected ?CacheVersionManager $versionManager = null
    ) {
        $this->cachePool = $cachePool ?? app(CacheItemPoolInterface::class);
        $this->versionManager = $versionManager ?? app(CacheVersionManager::class);
    }

    /**
     * Compute sales overview metrics (total revenue, count, average ticket, daily breakdown).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getSalesReport(array $filters = []): array
    {
        $cacheKey = $this->versionManager->makeKey(
            CacheVersionManager::TAG_REPORTS,
            'sales_' . md5(json_encode($filters))
        );
        $item = $this->cachePool->getItem($cacheKey);

        if ($item->isHit()) {
            return $item->get();
        }

        $baseQuery = Order::query();
        $this->applyDateFilters($baseQuery, $filters);

        $totalOrdersCount = (clone $baseQuery)->count();

        $approvedQuery = (clone $baseQuery)->where('status', Order::STATUS_APPROVED);
        $totalSales = (float) (clone $approvedQuery)->sum('total_amount');
        $approvedOrdersCount = (clone $approvedQuery)->count();

        $averageTicket = $approvedOrdersCount > 0
            ? round($totalSales / $approvedOrdersCount, 2)
            : 0.00;

        $conversionRate = $totalOrdersCount > 0
            ? round(($approvedOrdersCount / $totalOrdersCount) * 100, 2)
            : 0.00;

        // Daily trend of sales for chart rendering
        $dailySales = (clone $approvedQuery)
            ->selectRaw('DATE(created_at) as sale_date, SUM(total_amount) as daily_total, COUNT(*) as daily_count')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('sale_date', 'asc')
            ->get()
            ->map(function ($row) {
                return [
                    'date' => (string) $row->sale_date,
                    'total' => (float) $row->daily_total,
                    'count' => (int) $row->daily_count,
                ];
            })
            ->all();

        $result = [
            'total_sales' => $totalSales,
            'approved_orders_count' => $approvedOrdersCount,
            'total_orders_count' => $totalOrdersCount,
            'average_ticket' => $averageTicket,
            'conversion_rate' => $conversionRate,
            'daily_sales' => $dailySales,
        ];

        $item->set($result);
        $item->expiresAfter(300); // 5 minutes
        $this->cachePool->save($item);

        return $result;
    }

    /**
     * Get a query builder for orders filtered by date range and status.
     *
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getOrdersQuery(array $filters = []): Builder
    {
        $query = Order::query()->with(['items', 'user']);
        $this->applyDateFilters($query, $filters);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('created_at');
    }

    /**
     * Get the top-selling products by quantity sold and revenue generated.
     *
     * @param  array<string, mixed>  $filters
     * @param  int  $limit
     * @return \Illuminate\Support\Collection
     */
    public function getTopSellingProducts(array $filters = [], int $limit = 10): Collection
    {
        $cacheKey = $this->versionManager->makeKey(
            CacheVersionManager::TAG_REPORTS,
            'top_products_' . $limit . '_' . md5(json_encode($filters))
        );
        $item = $this->cachePool->getItem($cacheKey);

        if ($item->isHit()) {
            return $item->get();
        }

        $query = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', Order::STATUS_APPROVED);

        if (! empty($filters['date_from'])) {
            $query->whereDate('orders.created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('orders.created_at', '<=', $filters['date_to']);
        }

        $results = $query->select(
                'order_items.product_id',
                'order_items.product_name',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(order_items.subtotal) as total_revenue')
            )
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();

        $mapped = $results->map(function ($item) {
            $item->total_quantity = (int) $item->total_quantity;
            $item->total_revenue = (float) $item->total_revenue;
            return $item;
        });

        $item->set($mapped);
        $item->expiresAfter(300); // 5 minutes
        $this->cachePool->save($item);

        return $mapped;
    }

    /**
     * Compute PlaceToPay payment gateway metrics and statuses breakdown.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getPaymentStatusesReport(array $filters = []): array
    {
        $cacheKey = $this->versionManager->makeKey(
            CacheVersionManager::TAG_REPORTS,
            'payments_' . md5(json_encode($filters))
        );
        $item = $this->cachePool->getItem($cacheKey);

        if ($item->isHit()) {
            return $item->get();
        }

        $query = Order::query();
        $this->applyDateFilters($query, $filters);

        $totalTransactions = (clone $query)->count();
        $totalAmount = (float) (clone $query)->sum('total_amount');

        $statusRows = (clone $query)
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as amount'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $statuses = [
            Order::STATUS_APPROVED => ['name' => 'Aprobadas', 'count' => 0, 'amount' => 0.00, 'percentage' => 0.00],
            Order::STATUS_PENDING_PAYMENT => ['name' => 'Pendientes', 'count' => 0, 'amount' => 0.00, 'percentage' => 0.00],
            Order::STATUS_REJECTED => ['name' => 'Rechazadas', 'count' => 0, 'amount' => 0.00, 'percentage' => 0.00],
            Order::STATUS_CANCELLED => ['name' => 'Canceladas', 'count' => 0, 'amount' => 0.00, 'percentage' => 0.00],
            Order::STATUS_IN_CART => ['name' => 'En Carrito', 'count' => 0, 'amount' => 0.00, 'percentage' => 0.00],
        ];

        foreach ($statuses as $statusCode => &$data) {
            if ($statusRows->has($statusCode)) {
                $row = $statusRows->get($statusCode);
                $data['count'] = (int) $row->count;
                $data['amount'] = (float) $row->amount;
                $data['percentage'] = $totalTransactions > 0
                    ? round(($data['count'] / $totalTransactions) * 100, 2)
                    : 0.00;
            }
        }

        $result = [
            'total_transactions' => $totalTransactions,
            'total_amount' => $totalAmount,
            'statuses' => $statuses,
        ];

        $item->set($result);
        $item->expiresAfter(300); // 5 minutes
        $this->cachePool->save($item);

        return $result;
    }

    /**
     * Compute inventory alerts: low stock, out of stock, and dead/low rotation products.
     *
     * @param  int  $lowStockThreshold
     * @param  int  $daysInactive
     * @return array<string, mixed>
     */
    public function getInventoryAlerts(int $lowStockThreshold = 5, int $daysInactive = 30): array
    {
        $cacheKey = $this->versionManager->makeKey(
            CacheVersionManager::TAG_REPORTS,
            "inventory_alerts_{$lowStockThreshold}_{$daysInactive}"
        );
        $item = $this->cachePool->getItem($cacheKey);

        if ($item->isHit()) {
            return $item->get();
        }

        // Out of stock
        $outOfStock = Product::with('category')
            ->where('stock', 0)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Low stock (between 1 and lowStockThreshold)
        $lowStock = Product::with('category')
            ->where('stock', '>', 0)
            ->where('stock', '<=', $lowStockThreshold)
            ->where('is_active', true)
            ->orderBy('stock', 'asc')
            ->get();

        // Dead stock (products with stock > 0 that have zero approved sales in the last $daysInactive days)
        $sinceDate = Carbon::now()->subDays($daysInactive);
        $soldProductIds = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', Order::STATUS_APPROVED)
            ->where('orders.created_at', '>=', $sinceDate)
            ->distinct()
            ->pluck('order_items.product_id')
            ->filter();

        $deadStock = Product::with('category')
            ->where('stock', '>', 0)
            ->where('is_active', true)
            ->whereNotIn('id', $soldProductIds)
            ->orderByDesc('stock')
            ->get();

        $result = [
            'threshold' => $lowStockThreshold,
            'days_inactive' => $daysInactive,
            'out_of_stock' => $outOfStock,
            'out_of_stock_count' => $outOfStock->count(),
            'low_stock' => $lowStock,
            'low_stock_count' => $lowStock->count(),
            'dead_stock' => $deadStock,
            'dead_stock_count' => $deadStock->count(),
        ];

        $item->set($result);
        $item->expiresAfter(300); // 5 minutes
        $this->cachePool->save($item);

        return $result;
    }

    /**
     * Compile consolidated report data based on report configuration.
     *
     * @param  \App\Models\Report  $report
     * @return array<string, mixed>
     */
    public function compileReportData(Report $report): array
    {
        $params = $report->parameters ?? [];
        $lowThreshold = (int) ($params['low_stock_threshold'] ?? 5);
        $daysInactive = (int) ($params['days_inactive'] ?? 30);

        $sales = $this->getSalesReport($params);
        $payments = $this->getPaymentStatusesReport($params);
        $topProducts = $this->getTopSellingProducts($params, 15);
        $inventory = $this->getInventoryAlerts($lowThreshold, $daysInactive);
        $orders = $this->getOrdersQuery($params)->limit(100)->get();

        return [
            'report' => $report,
            'sales' => $sales,
            'payments' => $payments,
            'top_products' => $topProducts,
            'inventory' => $inventory,
            'orders' => $orders,
            'generated_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Generate and store a PDF document for the report.
     *
     * @param  \App\Models\Report  $report
     * @param  array<string, mixed>  $data
     * @return string Relative storage path of generated PDF
     */
    public function generatePdf(Report $report, array $data): string
    {
        $timestamp = date('Ymd_His');
        $fileName = "report_{$report->id}_{$timestamp}.pdf";
        $storagePath = "reports/{$fileName}";

        $pdf = Pdf::loadView('admin.reports.pdf', $data)
            ->setPaper('a4', 'portrait')
            ->setOption(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true]);

        Storage::disk('local')->put($storagePath, $pdf->output());

        return $storagePath;
    }

    /**
     * Generate and store an Excel document for the report.
     *
     * @param  \App\Models\Report  $report
     * @param  array<string, mixed>  $data
     * @return string Relative storage path of generated Excel
     */
    public function generateExcel(Report $report, array $data): string
    {
        $timestamp = date('Ymd_His');
        $fileName = "report_{$report->id}_{$timestamp}.xlsx";
        $storagePath = "reports/{$fileName}";

        Storage::disk('local')->makeDirectory('reports');
        $fullPath = Storage::disk('local')->path($storagePath);

        $rows = [];

        // Header Section
        $rows[] = ['Sección' => 'INFORMACIÓN GENERAL', 'Detalle' => 'MercaTodo - Reporte Administrativo', 'Métrica 1' => '', 'Métrica 2' => '', 'Métrica 3' => ''];
        $rows[] = ['Sección' => 'Reporte', 'Detalle' => $report->title, 'Métrica 1' => 'Tipo: ' . $report->type, 'Métrica 2' => 'Fecha: ' . now()->toDateTimeString(), 'Métrica 3' => ''];
        $rows[] = ['Sección' => '-----------------', 'Detalle' => '-----------------', 'Métrica 1' => '-----------------', 'Métrica 2' => '-----------------', 'Métrica 3' => '-----------------'];

        // Sales Summary
        $sales = $data['sales'] ?? [];
        $rows[] = ['Sección' => 'VENTAS', 'Detalle' => 'Ventas Totales Aprobadas', 'Métrica 1' => '$ ' . number_format($sales['total_sales'] ?? 0, 2), 'Métrica 2' => '', 'Métrica 3' => ''];
        $rows[] = ['Sección' => 'VENTAS', 'Detalle' => 'Órdenes Aprobadas', 'Métrica 1' => (string) ($sales['approved_orders_count'] ?? 0), 'Métrica 2' => '', 'Métrica 3' => ''];
        $rows[] = ['Sección' => 'VENTAS', 'Detalle' => 'Órdenes Totales', 'Métrica 1' => (string) ($sales['total_orders_count'] ?? 0), 'Métrica 2' => '', 'Métrica 3' => ''];
        $rows[] = ['Sección' => 'VENTAS', 'Detalle' => 'Ticket Promedio', 'Métrica 1' => '$ ' . number_format($sales['average_ticket'] ?? 0, 2), 'Métrica 2' => '', 'Métrica 3' => ''];
        $rows[] = ['Sección' => 'VENTAS', 'Detalle' => 'Tasa de Conversión', 'Métrica 1' => number_format($sales['conversion_rate'] ?? 0, 2) . ' %', 'Métrica 2' => '', 'Métrica 3' => ''];
        $rows[] = ['Sección' => '-----------------', 'Detalle' => '-----------------', 'Métrica 1' => '-----------------', 'Métrica 2' => '-----------------', 'Métrica 3' => '-----------------'];

        // Top Selling Products
        $rows[] = ['Sección' => 'TOP PRODUCTOS', 'Detalle' => 'Producto', 'Métrica 1' => 'Unidades Vendidas', 'Métrica 2' => 'Total Ingresos', 'Métrica 3' => ''];
        foreach ($data['top_products'] ?? [] as $topItem) {
            $rows[] = [
                'Sección' => 'TOP PRODUCTO',
                'Detalle' => $topItem->product_name ?? 'ID #' . $topItem->product_id,
                'Métrica 1' => (string) $topItem->total_quantity,
                'Métrica 2' => '$ ' . number_format($topItem->total_revenue, 2),
                'Métrica 3' => '',
            ];
        }
        $rows[] = ['Sección' => '-----------------', 'Detalle' => '-----------------', 'Métrica 1' => '-----------------', 'Métrica 2' => '-----------------', 'Métrica 3' => '-----------------'];

        // PlaceToPay Statuses
        $rows[] = ['Sección' => 'PLACETOPAY', 'Detalle' => 'Estado', 'Métrica 1' => 'Transacciones', 'Métrica 2' => 'Monto Total', 'Métrica 3' => 'Porcentaje'];
        foreach ($data['payments']['statuses'] ?? [] as $st) {
            $rows[] = [
                'Sección' => 'PLACETOPAY',
                'Detalle' => $st['name'],
                'Métrica 1' => (string) $st['count'],
                'Métrica 2' => '$ ' . number_format($st['amount'], 2),
                'Métrica 3' => number_format($st['percentage'], 2) . ' %',
            ];
        }
        $rows[] = ['Sección' => '-----------------', 'Detalle' => '-----------------', 'Métrica 1' => '-----------------', 'Métrica 2' => '-----------------', 'Métrica 3' => '-----------------'];

        // Inventory Alerts: Low Stock & Dead Stock
        $inventory = $data['inventory'] ?? [];
        $rows[] = ['Sección' => 'INVENTARIO - ALERTAS', 'Detalle' => 'Resumen de Stock', 'Métrica 1' => 'Agotados: ' . ($inventory['out_of_stock_count'] ?? 0), 'Métrica 2' => 'Bajo Stock: ' . ($inventory['low_stock_count'] ?? 0), 'Métrica 3' => 'Muertos / Sin Rotación: ' . ($inventory['dead_stock_count'] ?? 0)];

        foreach ($inventory['low_stock'] ?? [] as $prod) {
            $rows[] = [
                'Sección' => 'ALERTA STOCK BAJO',
                'Detalle' => $prod->name,
                'Métrica 1' => 'Stock Actual: ' . $prod->stock,
                'Métrica 2' => 'Precio: $' . number_format($prod->price, 2),
                'Métrica 3' => 'Categoría: ' . ($prod->category->name ?? 'N/A'),
            ];
        }

        foreach ($inventory['dead_stock'] ?? [] as $prod) {
            $rows[] = [
                'Sección' => 'ALERTA SIN ROTACIÓN',
                'Detalle' => $prod->name,
                'Métrica 1' => 'Stock Ocioso: ' . $prod->stock,
                'Métrica 2' => 'Precio: $' . number_format($prod->price, 2),
                'Métrica 3' => 'Sin ventas en ' . ($inventory['days_inactive'] ?? 30) . ' días',
            ];
        }

        (new FastExcel(collect($rows)))->export($fullPath);

        return $storagePath;
    }

    /**
     * Process report generation asynchronously, creating requested export files and updating status.
     *
     * @param  \App\Models\Report  $report
     * @return void
     */
    public function process(Report $report): void
    {
        try {
            $report->markAsProcessing();

            $data = $this->compileReportData($report);

            $pdfPath = null;
            $excelPath = null;

            if (in_array($report->format, [Report::FORMAT_PDF, Report::FORMAT_BOTH], true)) {
                $pdfPath = $this->generatePdf($report, $data);
            }

            if (in_array($report->format, [Report::FORMAT_XLSX, Report::FORMAT_BOTH], true)) {
                $excelPath = $this->generateExcel($report, $data);
            }

            // Prepare summary data to store in database for quick visualization
            $summary = [
                'sales' => $data['sales'],
                'payments' => $data['payments'],
                'top_products_count' => count($data['top_products']),
                'out_of_stock_count' => $data['inventory']['out_of_stock_count'] ?? 0,
                'low_stock_count' => $data['inventory']['low_stock_count'] ?? 0,
                'dead_stock_count' => $data['inventory']['dead_stock_count'] ?? 0,
            ];

            $report->markAsCompleted($summary, $pdfPath, $excelPath);

        } catch (\Throwable $e) {
            Log::error("Error procesando reporte #{$report->id}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            $report->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Apply date range filters to a query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array<string, mixed>  $filters
     * @return void
     */
    protected function applyDateFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
    }
}
