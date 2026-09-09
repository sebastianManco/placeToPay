@extends('layouts.app')

@section('content')
<div class="container-fluid px-4">
    <!-- Header with Actions -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="bi bi-graph-up text-primary"></i> Reportes y Métricas del Negocio
            </h1>
            <p class="text-muted mb-0">Monitoreo de ventas, transacciones PlaceToPay, rotación y alertas de inventario.</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#generateReportModal">
                <i class="bi bi-file-earmark-plus me-1"></i> Generar Nuevo Reporte (Encolado)
            </button>
        </div>
    </div>

    <!-- Feedback messages -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong>¡Excelente!</strong> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Atención:</strong> {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <!-- Global KPIs Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-primary shadow h-100 py-2 border-primary" style="border-left: 4px solid #0d6efd !important;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Ventas Aprobadas</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">${{ number_format($salesMetrics['total_sales'] ?? 0, 2) }} COP</div>
                            <small class="text-muted">{{ $salesMetrics['approved_orders_count'] ?? 0 }} pedidos aprobados</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-success shadow h-100 py-2 border-success" style="border-left: 4px solid #198754 !important;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Ticket Promedio</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">${{ number_format($salesMetrics['average_ticket'] ?? 0, 2) }} COP</div>
                            <small class="text-muted">Por orden aprobada</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-info shadow h-100 py-2 border-info" style="border-left: 4px solid #0dcaf0 !important;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Tasa de Conversión</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($salesMetrics['conversion_rate'] ?? 0, 2) }}%</div>
                            <small class="text-muted">{{ $salesMetrics['total_orders_count'] ?? 0 }} órdenes totales</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-warning shadow h-100 py-2 border-warning" style="border-left: 4px solid #ffc107 !important;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Alertas de Inventario</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <span class="text-danger">{{ $inventoryAlerts['out_of_stock_count'] ?? 0 }} agotados</span> / 
                                <span class="text-warning">{{ $inventoryAlerts['low_stock_count'] ?? 0 }} bajos</span>
                            </div>
                            <small class="text-muted">{{ $inventoryAlerts['dead_stock_count'] ?? 0 }} productos sin rotación</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="row mb-4">
        <!-- Sales Trend Chart -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Tendencia de Ventas Aprobadas</h6>
                    <span class="badge badge-light">Tiempo real</span>
                </div>
                <div class="card-body">
                    <canvas id="salesTrendChart" height="110"></canvas>
                </div>
            </div>
        </div>

        <!-- PlaceToPay Statuses Chart -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 bg-white">
                    <h6 class="m-0 font-weight-bold text-primary">Distribución de Pagos PlaceToPay</h6>
                </div>
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <div style="width: 100%; max-width: 280px;">
                        <canvas id="paymentStatusesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Top Selling Products Chart -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 bg-white">
                    <h6 class="m-0 font-weight-bold text-primary">Top 5 Productos Más Vendidos (Unidades)</h6>
                </div>
                <div class="card-body">
                    <canvas id="topProductsChart" height="130"></canvas>
                </div>
            </div>
        </div>

        <!-- Inventory Alerts Breakdown -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 bg-white">
                    <h6 class="m-0 font-weight-bold text-primary">Diagnóstico y Salud del Catálogo</h6>
                </div>
                <div class="card-body">
                    <canvas id="inventoryHealthChart" height="130"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Historial de Reportes Encolados -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Historial de Reportes Generados</h6>
            <button class="btn btn-sm btn-outline-secondary" onclick="window.location.reload();">
                <i class="bi bi-arrow-clockwise"></i> Refrescar Lista
            </button>
        </div>
        <div class="card-body">
            @if($reports->isEmpty())
                <div class="text-center py-5 text-muted">
                    <p class="mb-0">No se ha solicitado ningún reporte aún. Pulsa el botón superior para generar uno.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Título</th>
                                <th>Tipo</th>
                                <th>Formato</th>
                                <th>Rango de Fechas</th>
                                <th>Estado</th>
                                <th>Solicitado Por</th>
                                <th>Fecha Solicitud</th>
                                <th class="text-end">Descargas / Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($reports as $rep)
                                <tr id="report-row-{{ $rep->id }}" data-report-id="{{ $rep->id }}" data-status="{{ $rep->status }}">
                                    <td><strong>#{{ $rep->id }}</strong></td>
                                    <td>
                                        <a href="{{ route('admin.reports.show', $rep) }}" class="text-dark font-weight-bold">
                                            {{ $rep->title }}
                                        </a>
                                    </td>
                                    <td><span class="badge badge-secondary">{{ strtoupper($rep->type) }}</span></td>
                                    <td><span class="badge badge-info">{{ strtoupper($rep->format) }}</span></td>
                                    <td>
                                        @if(!empty($rep->parameters['date_from']) || !empty($rep->parameters['date_to']))
                                            <small>{{ $rep->parameters['date_from'] ?? 'Inicio' }} &rarr; {{ $rep->parameters['date_to'] ?? 'Hoy' }}</small>
                                        @else
                                            <small class="text-muted">Histórico Total</small>
                                        @endif
                                    </td>
                                    <td class="status-cell">
                                        @if($rep->status === 'completed')
                                            <span class="badge badge-success">Completado</span>
                                        @elseif($rep->status === 'processing')
                                            <span class="badge badge-primary">Procesando...</span>
                                        @elseif($rep->status === 'failed')
                                            <span class="badge badge-danger">Fallido</span>
                                        @else
                                            <span class="badge badge-warning">En Cola</span>
                                        @endif
                                    </td>
                                    <td>{{ $rep->user->name ?? 'Admin' }}</td>
                                    <td><small>{{ $rep->created_at->format('d/m/Y H:i') }}</small></td>
                                    <td class="text-end actions-cell">
                                        <div class="btn-group" role="group">
                                            @if($rep->hasPdf())
                                                <a href="{{ route('admin.reports.download', ['report' => $rep, 'format' => 'pdf']) }}" class="btn btn-sm btn-outline-danger" title="Descargar PDF">
                                                    PDF
                                                </a>
                                            @endif
                                            @if($rep->hasExcel())
                                                <a href="{{ route('admin.reports.download', ['report' => $rep, 'format' => 'xlsx']) }}" class="btn btn-sm btn-outline-success" title="Descargar Excel">
                                                    Excel
                                                </a>
                                            @endif
                                            <a href="{{ route('admin.reports.show', $rep) }}" class="btn btn-sm btn-outline-primary" title="Ver Detalle">
                                                Ver
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-center mt-3">
                    {{ $reports->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Modal para Generar Nuevo Reporte -->
<div class="modal fade" id="generateReportModal" tabindex="-1" role="dialog" aria-labelledby="generateReportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form action="{{ route('admin.reports.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold" id="generateReportModalLabel">Generar Nuevo Reporte</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group mb-3">
                    <label for="title" class="form-label font-weight-bold">Título o Nombre del Reporte <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="title" class="form-control" placeholder="Ej: Reporte Gerencial Septiembre 2026" required value="{{ old('title') }}">
                </div>

                <div class="row">
                    <div class="col-md-6 form-group mb-3">
                        <label for="type" class="form-label font-weight-bold">Tipo de Reporte <span class="text-danger">*</span></label>
                        <select name="type" id="type" class="form-control" required>
                            <option value="complete">Completo (Ventas, Pagos, Inventario)</option>
                            <option value="sales">Ventas y Facturación</option>
                            <option value="orders">Órdenes por Fechas</option>
                            <option value="top_products">Productos Más Vendidos</option>
                            <option value="payments">Estados PlaceToPay</option>
                            <option value="inventory_alerts">Alertas Stock y Rotación</option>
                        </select>
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label for="format" class="form-label font-weight-bold">Formato de Exportación <span class="text-danger">*</span></label>
                        <select name="format" id="format" class="form-control" required>
                            <option value="both">Ambos (PDF + Excel)</option>
                            <option value="pdf">Solo PDF</option>
                            <option value="xlsx">Solo Excel (.xlsx)</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group mb-3">
                        <label for="date_from" class="form-label">Fecha Desde</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" value="{{ old('date_from') }}">
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label for="date_to" class="form-label">Fecha Hasta</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" value="{{ old('date_to') }}">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group mb-3">
                        <label for="low_stock_threshold" class="form-label">Umbral Stock Bajo</label>
                        <input type="number" name="low_stock_threshold" id="low_stock_threshold" class="form-control" value="5" min="1" max="1000">
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label for="days_inactive" class="form-label">Días Sin Ventas (Rotación)</label>
                        <input type="number" name="days_inactive" id="days_inactive" class="form-control" value="30" min="1" max="365">
                    </div>
                </div>

                <div class="alert alert-info py-2 mb-0">
                    <small><i class="bi bi-info-circle"></i> Este reporte se procesará de forma asíncrona mediante un trabajo encolado. Podrás descargarlo inmediatamente una vez finalizado.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Encolar y Generar</button>
            </div>
        </form>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Sales Trend Chart
    const salesData = @json($salesMetrics['daily_sales'] ?? []);
    const salesLabels = salesData.map(item => item.date);
    const salesTotals = salesData.map(item => item.total);

    const ctxSales = document.getElementById('salesTrendChart')?.getContext('2d');
    if (ctxSales) {
        new Chart(ctxSales, {
            type: 'line',
            data: {
                labels: salesLabels.length ? salesLabels : ['Sin datos'],
                datasets: [{
                    label: 'Ventas Aprobadas ($ COP)',
                    data: salesTotals.length ? salesTotals : [0],
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(val) { return '$' + Number(val).toLocaleString(); }
                        }
                    }
                }
            }
        });
    }

    // 2. PlaceToPay Statuses Doughnut Chart
    const paymentStatuses = @json($paymentMetrics['statuses'] ?? []);
    const paymentLabels = Object.values(paymentStatuses).map(s => s.name);
    const paymentCounts = Object.values(paymentStatuses).map(s => s.count);

    const ctxPayments = document.getElementById('paymentStatusesChart')?.getContext('2d');
    if (ctxPayments) {
        new Chart(ctxPayments, {
            type: 'doughnut',
            data: {
                labels: paymentLabels,
                datasets: [{
                    data: paymentCounts,
                    backgroundColor: ['#16a34a', '#eab308', '#dc2626', '#64748b', '#9333ea'],
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    // 3. Top Products Chart
    const topProducts = @json($topProducts ?? []);
    const prodLabels = topProducts.map(p => p.product_name || ('ID #' + p.product_id));
    const prodQuantities = topProducts.map(p => p.total_quantity);

    const ctxTop = document.getElementById('topProductsChart')?.getContext('2d');
    if (ctxTop) {
        new Chart(ctxTop, {
            type: 'bar',
            data: {
                labels: prodLabels.length ? prodLabels : ['Sin ventas'],
                datasets: [{
                    label: 'Unidades Vendidas',
                    data: prodQuantities.length ? prodQuantities : [0],
                    backgroundColor: '#3b82f6',
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                scales: {
                    x: { beginAtZero: true }
                }
            }
        });
    }

    // 4. Inventory Health Chart
    const inventory = @json($inventoryAlerts ?? []);
    const ctxInv = document.getElementById('inventoryHealthChart')?.getContext('2d');
    if (ctxInv) {
        new Chart(ctxInv, {
            type: 'bar',
            data: {
                labels: ['Agotados (Stock 0)', 'Stock Bajo', 'Sin Rotación / Muertos'],
                datasets: [{
                    label: 'Cantidad de Productos',
                    data: [
                        inventory.out_of_stock_count || 0,
                        inventory.low_stock_count || 0,
                        inventory.dead_stock_count || 0
                    ],
                    backgroundColor: ['#ef4444', '#f59e0b', '#6b7280'],
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // 5. Polling for pending/processing reports
    const pendingRows = document.querySelectorAll('tr[data-status="pending"], tr[data-status="processing"]');
    if (pendingRows.length > 0) {
        const intervalId = setInterval(() => {
            let activeCount = 0;
            pendingRows.forEach(row => {
                const reportId = row.getAttribute('data-report-id');
                fetch(`/admin/reports/${reportId}/status`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.is_completed) {
                            row.setAttribute('data-status', 'completed');
                            const statusCell = row.querySelector('.status-cell');
                            if (statusCell) {
                                statusCell.innerHTML = '<span class="badge badge-success">Completado</span>';
                            }
                            const actionsCell = row.querySelector('.actions-cell .btn-group');
                            if (actionsCell) {
                                let html = '';
                                if (data.has_pdf) {
                                    html += `<a href="${data.download_pdf_url}" class="btn btn-sm btn-outline-danger">PDF</a>`;
                                }
                                if (data.has_excel) {
                                    html += `<a href="${data.download_excel_url}" class="btn btn-sm btn-outline-success">Excel</a>`;
                                }
                                html += `<a href="/admin/reports/${data.id}" class="btn btn-sm btn-outline-primary">Ver</a>`;
                                actionsCell.innerHTML = html;
                            }
                        } else if (data.is_failed) {
                            row.setAttribute('data-status', 'failed');
                            const statusCell = row.querySelector('.status-cell');
                            if (statusCell) {
                                statusCell.innerHTML = '<span class="badge badge-danger">Fallido</span>';
                            }
                        } else {
                            activeCount++;
                        }
                    })
                    .catch(err => console.error(err));
            });

            if (activeCount === 0) {
                clearInterval(intervalId);
            }
        }, 3000);
    }
});
</script>
@endsection