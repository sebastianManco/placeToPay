<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $report->title }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            font-size: 11px;
            margin: 20px;
            line-height: 1.4;
        }
        .header {
            border-bottom: 2px solid #2563eb;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header table {
            width: 100%;
        }
        .brand {
            font-size: 20px;
            font-weight: bold;
            color: #1e3a8a;
        }
        .title {
            font-size: 16px;
            font-weight: bold;
            margin-top: 5px;
            color: #1f2937;
        }
        .meta {
            text-align: right;
            font-size: 10px;
            color: #6b7280;
        }
        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #1e40af;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
            margin-top: 18px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .kpi-table {
            width: 100%;
            margin-bottom: 15px;
            border-collapse: collapse;
        }
        .kpi-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px;
            text-align: center;
        }
        .kpi-label {
            font-size: 9px;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .kpi-value {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table.data-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: bold;
            text-align: left;
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            font-size: 10px;
        }
        table.data-table td {
            padding: 5px 8px;
            border: 1px solid #e2e8f0;
            font-size: 10px;
        }
        table.data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
        }
        .badge-success { background-color: #dcfce7; color: #15803d; }
        .badge-warning { background-color: #fef9c3; color: #854d0e; }
        .badge-danger { background-color: #fee2e2; color: #b91c1c; }
        .badge-info { background-color: #e0f2fe; color: #0369a1; }
        .badge-secondary { background-color: #f3f4f6; color: #4b5563; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .page-break { page-break-after: always; }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }
    </style>
</head>
<body>
    <div class="footer">
        MercaTodo &copy; {{ date('Y') }} - Sistema de Reportes y Gestión de Negocio | Página generada el {{ $generated_at }}
    </div>

    <!-- Encabezado -->
    <div class="header">
        <table>
            <tr>
                <td>
                    <div class="brand">MercaTodo</div>
                    <div class="title">{{ $report->title }}</div>
                    <div style="font-size: 10px; color: #4b5563;">Tipo de Reporte: {{ strtoupper($report->type) }}</div>
                </td>
                <td class="meta">
                    <div><strong>Fecha Emisión:</strong> {{ $generated_at }}</div>
                    @if (!empty($report->parameters['date_from']) || !empty($report->parameters['date_to']))
                        <div><strong>Rango:</strong> {{ $report->parameters['date_from'] ?? 'Inicio' }} al {{ $report->parameters['date_to'] ?? 'Hoy' }}</div>
                    @endif
                    <div><strong>Generado Por:</strong> {{ $report->user->name ?? 'Administrador' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Resumen de Ventas (KPIs) -->
    <div class="section-title">1. Resumen Ejecutivo de Ventas</div>
    <table class="kpi-table">
        <tr>
            <td style="width: 20%; padding: 4px;">
                <div class="kpi-card">
                    <div class="kpi-label">Ventas Aprobadas</div>
                    <div class="kpi-value" style="color: #15803d;">$ {{ number_format($sales['total_sales'] ?? 0, 2) }}</div>
                </div>
            </td>
            <td style="width: 20%; padding: 4px;">
                <div class="kpi-card">
                    <div class="kpi-label">Órdenes Aprobadas</div>
                    <div class="kpi-value">{{ $sales['approved_orders_count'] ?? 0 }}</div>
                </div>
            </td>
            <td style="width: 20%; padding: 4px;">
                <div class="kpi-card">
                    <div class="kpi-label">Órdenes Totales</div>
                    <div class="kpi-value">{{ $sales['total_orders_count'] ?? 0 }}</div>
                </div>
            </td>
            <td style="width: 20%; padding: 4px;">
                <div class="kpi-card">
                    <div class="kpi-label">Ticket Promedio</div>
                    <div class="kpi-value">$ {{ number_format($sales['average_ticket'] ?? 0, 2) }}</div>
                </div>
            </td>
            <td style="width: 20%; padding: 4px;">
                <div class="kpi-card">
                    <div class="kpi-label">Conversión</div>
                    <div class="kpi-value">{{ number_format($sales['conversion_rate'] ?? 0, 2) }}%</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Estados de Pagos PlaceToPay -->
    <div class="section-title">2. Estados de Pagos con Pasarela PlaceToPay</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Estado Pasarela</th>
                <th class="text-center">Transacciones</th>
                <th class="text-right">Monto Total</th>
                <th class="text-right">Participación %</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments['statuses'] as $key => $st)
                <tr>
                    <td>
                        @if($key === 'approved')
                            <span class="badge badge-success">Aprobada</span>
                        @elseif($key === 'pending_payment')
                            <span class="badge badge-warning">Pendiente</span>
                        @elseif($key === 'rejected')
                            <span class="badge badge-danger">Rechazada</span>
                        @elseif($key === 'cancelled')
                            <span class="badge badge-secondary">Cancelada</span>
                        @else
                            <span class="badge badge-info">{{ $st['name'] }}</span>
                        @endif
                    </td>
                    <td class="text-center">{{ $st['count'] }}</td>
                    <td class="text-right">$ {{ number_format($st['amount'], 2) }} COP</td>
                    <td class="text-right">{{ number_format($st['percentage'], 2) }}%</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Productos Más Vendidos -->
    <div class="section-title">3. Productos Más Vendidos (Ranking en Pedidos Aprobados)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 10%;">Pos.</th>
                <th>Nombre del Producto</th>
                <th class="text-center" style="width: 25%;">Unidades Vendidas</th>
                <th class="text-right" style="width: 25%;">Total Generado ($)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($top_products as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td><strong>{{ $item->product_name ?? 'ID #' . $item->product_id }}</strong></td>
                    <td class="text-center">{{ $item->total_quantity }}</td>
                    <td class="text-right">$ {{ number_format($item->total_revenue, 2) }} COP</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center" style="color: #64748b;">No hay registros de ventas aprobadas en este rango.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Alertas de Inventario y Rotación -->
    <div class="section-title">4. Rotación y Alertas de Inventario (Stock Bajo / Agotados / Sin Rotación)</div>
    
    <table class="kpi-table" style="margin-bottom: 10px;">
        <tr>
            <td style="width: 33%; padding: 4px;">
                <div class="kpi-card" style="border-left: 4px solid #b91c1c;">
                    <div class="kpi-label">Productos Agotados (Stock 0)</div>
                    <div class="kpi-value" style="color: #b91c1c;">{{ $inventory['out_of_stock_count'] ?? 0 }}</div>
                </div>
            </td>
            <td style="width: 33%; padding: 4px;">
                <div class="kpi-card" style="border-left: 4px solid #d97706;">
                    <div class="kpi-label">Stock Bajo (&le; {{ $inventory['threshold'] ?? 5 }})</div>
                    <div class="kpi-value" style="color: #d97706;">{{ $inventory['low_stock_count'] ?? 0 }}</div>
                </div>
            </td>
            <td style="width: 33%; padding: 4px;">
                <div class="kpi-card" style="border-left: 4px solid #475569;">
                    <div class="kpi-label">Baja Rotación / Muertos (&gt; {{ $inventory['days_inactive'] ?? 30 }}d sin venta)</div>
                    <div class="kpi-value" style="color: #475569;">{{ $inventory['dead_stock_count'] ?? 0 }}</div>
                </div>
            </td>
        </tr>
    </table>

    @if(($inventory['low_stock_count'] ?? 0) > 0)
        <div style="font-weight: bold; margin-top: 6px; margin-bottom: 4px; color: #b45309;">Alertas: Productos con Stock Próximo a Agotarse</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th class="text-center">Stock Actual</th>
                    <th class="text-right">Precio Unitario</th>
                </tr>
            </thead>
            <tbody>
                @foreach($inventory['low_stock']->take(10) as $prod)
                    <tr>
                        <td>{{ $prod->name }}</td>
                        <td>{{ $prod->category->name ?? 'General' }}</td>
                        <td class="text-center"><span class="badge badge-warning">{{ $prod->stock }} unidades</span></td>
                        <td class="text-right">$ {{ number_format($prod->price, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(($inventory['dead_stock_count'] ?? 0) > 0)
        <div style="font-weight: bold; margin-top: 6px; margin-bottom: 4px; color: #475569;">Alertas: Productos "Muertos" / Sin Rotación Ocupando Inventario</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Producto Ocioso</th>
                    <th>Categoría</th>
                    <th class="text-center">Stock Inmovilizado</th>
                    <th class="text-right">Capital Parado ($)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($inventory['dead_stock']->take(10) as $prod)
                    <tr>
                        <td>{{ $prod->name }}</td>
                        <td>{{ $prod->category->name ?? 'General' }}</td>
                        <td class="text-center"><span class="badge badge-secondary">{{ $prod->stock }} unidades</span></td>
                        <td class="text-right">$ {{ number_format($prod->stock * $prod->price, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

</body>
</html>
