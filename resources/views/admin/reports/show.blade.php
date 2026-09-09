@extends('layouts.app')

@section('content')
<div class="container py-4">
    <!-- Breadcrumb & Back -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="{{ route('admin.reports.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
                &larr; Volver al Panel de Reportes
            </a>
            <h1 class="h3 mb-0 text-gray-800">{{ $report->title }}</h1>
            <span class="text-muted">Reporte #{{ $report->id }} &bull; Tipo: {{ strtoupper($report->type) }}</span>
        </div>
        <div>
            <div class="btn-group" role="group">
                @if($report->hasPdf())
                    <a href="{{ route('admin.reports.download', ['report' => $report, 'format' => 'pdf']) }}" class="btn btn-danger">
                        <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
                    </a>
                @endif
                @if($report->hasExcel())
                    <a href="{{ route('admin.reports.download', ['report' => $report, 'format' => 'xlsx']) }}" class="btn btn-success">
                        <i class="bi bi-file-earmark-excel"></i> Descargar Excel (.xlsx)
                    </a>
                @endif
            </div>
        </div>
    </div>

    <!-- Status Alert -->
    @if($report->isCompleted())
        <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
            <div>
                <strong>¡Reporte Completado!</strong> Generado con éxito el {{ $report->completed_at?->format('d/m/Y H:i:s') }}. Puedes descargarlo en los formatos solicitados.
            </div>
        </div>
    @elseif($report->isFailed())
        <div class="alert alert-danger mb-4" role="alert">
            <strong>Error al generar el reporte:</strong> {{ $report->error_message ?? 'Ocurrió un error inesperado durante el procesamiento en cola.' }}
        </div>
    @else
        <div class="alert alert-warning mb-4" role="alert">
            <strong>En proceso:</strong> Este reporte se encuentra actualmente en cola de ejecución. Se actualizará automáticamente en breve.
        </div>
    @endif

    <!-- Metadata Details Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold text-primary">Parámetros y Configuración</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>Estado:</strong>
                    @if($report->isCompleted())
                        <span class="badge badge-success">Completado</span>
                    @elseif($report->isFailed())
                        <span class="badge badge-danger">Fallido</span>
                    @else
                        <span class="badge badge-warning">En Cola</span>
                    @endif
                </div>
                <div class="col-md-3">
                    <strong>Formato Solicitado:</strong>
                    <span class="badge badge-info">{{ strtoupper($report->format) }}</span>
                </div>
                <div class="col-md-3">
                    <strong>Rango de Fechas:</strong>
                    @if(!empty($report->parameters['date_from']) || !empty($report->parameters['date_to']))
                        {{ $report->parameters['date_from'] ?? 'Inicio' }} al {{ $report->parameters['date_to'] ?? 'Hoy' }}
                    @else
                        Histórico completo
                    @endif
                </div>
                <div class="col-md-3">
                    <strong>Solicitado Por:</strong>
                    {{ $report->user->name ?? 'Administrador' }}
                </div>
            </div>
        </div>
    </div>

    @if($report->summary_data)
        @php $summary = $report->summary_data; @endphp
        <!-- Resumen de Ventas Guardado -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary">Métricas Clave Consolidadas</h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3 border-right">
                        <div class="text-muted small uppercase">Ventas Aprobadas</div>
                        <div class="h4 font-weight-bold text-success mb-0">${{ number_format($summary['sales']['total_sales'] ?? 0, 2) }}</div>
                    </div>
                    <div class="col-md-3 border-right">
                        <div class="text-muted small uppercase">Órdenes Aprobadas</div>
                        <div class="h4 font-weight-bold text-dark mb-0">{{ $summary['sales']['approved_orders_count'] ?? 0 }}</div>
                    </div>
                    <div class="col-md-3 border-right">
                        <div class="text-muted small uppercase">Ticket Promedio</div>
                        <div class="h4 font-weight-bold text-primary mb-0">${{ number_format($summary['sales']['average_ticket'] ?? 0, 2) }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small uppercase">Conversión</div>
                        <div class="h4 font-weight-bold text-info mb-0">{{ number_format($summary['sales']['conversion_rate'] ?? 0, 2) }}%</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alertas de Inventario Guardadas -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary">Diagnóstico de Stock al Momento del Reporte</h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="text-danger small font-weight-bold">PRODUCTOS AGOTADOS</div>
                        <div class="h3 text-danger font-weight-bold">{{ $summary['out_of_stock_count'] ?? 0 }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-warning small font-weight-bold">PRODUCTOS CON STOCK BAJO</div>
                        <div class="h3 text-warning font-weight-bold">{{ $summary['low_stock_count'] ?? 0 }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-secondary small font-weight-bold">PRODUCTOS SIN ROTACIÓN ("MUERTOS")</div>
                        <div class="h3 text-secondary font-weight-bold">{{ $summary['dead_stock_count'] ?? 0 }}</div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection