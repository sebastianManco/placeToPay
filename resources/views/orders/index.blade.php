@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 font-weight-bold text-dark mb-1">{{ __('Historial de mis Pedidos') }}</h1>
            <p class="text-muted small mb-0">{{ __('Consulta y realiza seguimiento al estado de todas tus compras.') }}</p>
        </div>
        <a href="{{ route('showcase.index') }}" class="btn btn-outline-primary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-shop mr-1" viewBox="0 0 16 16">
                <path d="M2.97 1.35A1 1 0 0 1 3.73 1h8.54a1 1 0 0 1 .76.35l2.609 3.044A1.5 1.5 0 0 1 16 5.37v.255a2.375 2.375 0 0 1-4.25 1.458A2.371 2.371 0 0 1 9.75 8l-.001.002A2.374 2.374 0 0 1 8 7.5a2.374 2.374 0 0 1-1.75.502 2.371 2.371 0 0 1-2-1.017A2.375 2.375 0 0 1 0 5.625V5.37a1.5 1.5 0 0 1 .361-.976l2.61-3.045zm1.78 4.275a1.375 1.375 0 0 0 2.75 0 .5.5 0 0 1 1 0 1.375 1.375 0 0 0 2.75 0 .5.5 0 0 1 1 0 1.375 1.375 0 1 0 2.75 0V5.37a.5.5 0 0 0-.12-.325L12.27 2H3.73L1.12 5.045A.5.5 0 0 0 1 5.37v.255a1.375 1.375 0 0 0 2.75 0 .5.5 0 0 1 1 0zM1.5 8.5A.5.5 0 0 1 2 9v6h12V9a.5.5 0 0 1 1 0v6h.5a.5.5 0 0 1 0 1H.5a.5.5 0 0 1 0-1H1V9a.5.5 0 0 1 .5-.5zM4 15h8V9.5a.5.5 0 0 0-.5-.5h-7a.5.5 0 0 0-.5.5V15z"/>
            </svg>
            {{ __('Ir a la Tienda') }}
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    {{-- Filtros de búsqueda --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('orders.index') }}" class="row align-items-end">
                <div class="col-md-3 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">{{ __('Referencia:') }}</label>
                    <input type="text" 
                           name="search" 
                           class="form-control form-control-sm" 
                           placeholder="Buscar por referencia..." 
                           value="{{ request('search') }}">
                </div>
                <div class="col-md-3 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">{{ __('Estado:') }}</label>
                    <select name="status" class="form-control form-control-sm custom-select custom-select-sm">
                        <option value="">-- Todos los estados --</option>
                        <option value="pending_payment" {{ request('status') === 'pending_payment' ? 'selected' : '' }}>
                            {{ __('Pendiente de Pago') }}
                        </option>
                        <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>
                            {{ __('Aprobado') }}
                        </option>
                        <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>
                            {{ __('Rechazado') }}
                        </option>
                        <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>
                            {{ __('Cancelado') }}
                        </option>
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">{{ __('Desde:') }}</label>
                    <input type="date" 
                           name="date_from" 
                           class="form-control form-control-sm" 
                           value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">{{ __('Hasta:') }}</label>
                    <input type="date" 
                           name="date_to" 
                           class="form-control form-control-sm" 
                           value="{{ request('date_to') }}">
                </div>
                <div class="col-md-2 mb-2 d-flex">
                    <button type="submit" class="btn btn-primary btn-sm btn-block mr-1">
                        {{ __('Filtrar') }}
                    </button>
                    @if (request()->hasAny(['search', 'status', 'date_from', 'date_to']))
                        <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary btn-sm" title="{{ __('Limpiar') }}">
                            {{ __('Limpiar') }}
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Listado de Pedidos --}}
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            @if ($orders->isEmpty())
                <div class="text-center py-5">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" class="bi bi-inbox text-muted mb-3" viewBox="0 0 16 16">
                        <path d="M4.98 4a.5.5 0 0 0-.39.188L1.54 8H6a.5.5 0 0 1 .5.5 1.5 1.5 0 1 0 3 0A.5.5 0 0 1 10 8h4.46l-3.05-3.812A.5.5 0 0 0 11.02 4H4.98zm-1.17-.437A1.5 1.5 0 0 1 4.98 3h6.04a1.5 1.5 0 0 1 1.17.563l3.7 4.625a1 1 0 0 1 .11.5v6.5A1.5 1.5 0 0 1 14.5 16h-13A1.5 1.5 0 0 1 0 14.5v-6.5a1 1 0 0 1 .11-.5l3.7-4.625zM1 9v5.5a.5.5 0 0 0 .5.5h13a.5.5 0 0 0 .5-.5V9h-3.915a2.5 2.5 0 0 1-4.17 0H1z"/>
                    </svg>
                    <h5 class="text-muted font-weight-bold">{{ __('No se encontraron pedidos.') }}</h5>
                    <p class="text-muted small mb-3">{{ __('Aún no has realizado compras o no coinciden con los filtros aplicados.') }}</p>
                    <a href="{{ route('showcase.index') }}" class="btn btn-primary btn-sm">
                        {{ __('Explorar catálogo de productos') }}
                    </a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ __('Referencia') }}</th>
                                <th>{{ __('Fecha') }}</th>
                                <th class="text-center">{{ __('Artículos') }}</th>
                                <th class="text-right">{{ __('Total') }}</th>
                                <th class="text-center">{{ __('Estado') }}</th>
                                <th class="text-center" style="width: 140px;">{{ __('Acción') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $order)
                                <tr>
                                    <td class="font-weight-bold text-dark align-middle">
                                        #{{ $order->reference }}
                                    </td>
                                    <td class="text-muted align-middle">
                                        {{ $order->created_at ? $order->created_at->format('d/m/Y H:i') : '-' }}
                                    </td>
                                    <td class="text-center align-middle">
                                        <span class="badge badge-light border">
                                            {{ $order->getTotalQuantity() }} {{ __('unds') }} ({{ $order->items->count() }} {{ __('ítems') }})
                                        </span>
                                    </td>
                                    <td class="text-right font-weight-bold text-primary align-middle">
                                        ${{ number_format($order->total_amount, 2, ',', '.') }} {{ $order->currency }}
                                    </td>
                                    <td class="text-center align-middle">
                                        @if ($order->status === 'approved')
                                            <span class="badge badge-success px-2 py-1">{{ __('Aprobado') }}</span>
                                        @elseif ($order->status === 'pending_payment')
                                            <span class="badge badge-warning px-2 py-1 text-dark">{{ __('Pendiente de Pago') }}</span>
                                        @elseif ($order->status === 'rejected')
                                            <span class="badge badge-danger px-2 py-1">{{ __('Rechazado') }}</span>
                                        @elseif ($order->status === 'cancelled')
                                            <span class="badge badge-secondary px-2 py-1">{{ __('Cancelado') }}</span>
                                        @else
                                            <span class="badge badge-info px-2 py-1">{{ $order->status }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center align-middle">
                                        <a href="{{ route('orders.show', $order->id) }}" class="btn btn-outline-primary btn-sm">
                                            {{ __('Ver Detalle') }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @if ($orders->hasPages())
            <div class="card-footer bg-white d-flex justify-content-center py-3">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
