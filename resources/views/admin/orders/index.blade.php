@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 font-weight-bold text-dark mb-1">{{ __('Panel de Gestión de Pedidos') }}</h1>
            <p class="text-muted small mb-0">{{ __('Visualiza, filtra y administra todas las órdenes de compra realizadas en la tienda.') }}</p>
        </div>
        <span class="badge bg-primary text-white p-2 fs-6">
            {{ __('Total Filtrados:') }} {{ $orders->total() }}
        </span>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    {{-- Métricas Rápidas --}}
    <div class="row mb-4">
        <div class="col-md">
            <div class="card shadow-sm border-0 text-center py-3 mb-2">
                <div class="text-muted small font-weight-bold text-uppercase">{{ __('Total Órdenes') }}</div>
                <div class="h3 font-weight-bold text-dark mb-0">{{ $totalOrders }}</div>
            </div>
        </div>
        <div class="col-md">
            <div class="card shadow-sm border-0 text-center py-3 mb-2 border-left-warning">
                <div class="text-muted small font-weight-bold text-uppercase">{{ __('Pendientes') }}</div>
                <div class="h3 font-weight-bold text-warning mb-0">{{ $pendingOrders }}</div>
            </div>
        </div>
        <div class="col-md">
            <div class="card shadow-sm border-0 text-center py-3 mb-2 border-left-success">
                <div class="text-muted small font-weight-bold text-uppercase">{{ __('Aprobadas') }}</div>
                <div class="h3 font-weight-bold text-success mb-0">{{ $approvedOrders }}</div>
            </div>
        </div>
        <div class="col-md">
            <div class="card shadow-sm border-0 text-center py-3 mb-2 border-left-danger">
                <div class="text-muted small font-weight-bold text-uppercase">{{ __('Rechazadas') }}</div>
                <div class="h3 font-weight-bold text-danger mb-0">{{ $rejectedOrders }}</div>
            </div>
        </div>
        <div class="col-md">
            <div class="card shadow-sm border-0 text-center py-3 mb-2 border-left-secondary">
                <div class="text-muted small font-weight-bold text-uppercase">{{ __('Canceladas') }}</div>
                <div class="h3 font-weight-bold text-secondary mb-0">{{ $cancelledOrders }}</div>
            </div>
        </div>
    </div>

    {{-- Formulario de Filtros Avanzados --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.orders.index') }}" class="row align-items-end">
                <div class="col-lg-3 col-md-6 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">{{ __('Búsqueda General / Referencia:') }}</label>
                    <input type="text" 
                           name="search" 
                           class="form-control form-control-sm" 
                           placeholder="Referencia o datos..." 
                           value="{{ request('search') }}">
                </div>

                <div class="col-lg-3 col-md-6 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">{{ __('Filtrar por Cliente:') }}</label>
                    <input type="text" 
                           name="client" 
                           class="form-control form-control-sm" 
                           placeholder="Nombre, email o CC..." 
                           value="{{ request('client') }}">
                </div>

                <div class="col-lg-2 col-md-4 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">{{ __('Estado:') }}</label>
                    <select name="status" class="form-control form-control-sm custom-select custom-select-sm">
                        <option value="">-- Todos --</option>
                        <option value="pending_payment" {{ request('status') === 'pending_payment' ? 'selected' : '' }}>Pendiente Pago</option>
                        <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Aprobado</option>
                        <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rechazado</option>
                        <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelado</option>
                        <option value="in_cart" {{ request('status') === 'in_cart' ? 'selected' : '' }}>En Carrito</option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-4 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">{{ __('Desde:') }}</label>
                    <input type="date" 
                           name="date_from" 
                           class="form-control form-control-sm" 
                           value="{{ request('date_from') }}">
                </div>

                <div class="col-lg-2 col-md-4 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">{{ __('Hasta:') }}</label>
                    <input type="date" 
                           name="date_to" 
                           class="form-control form-control-sm" 
                           value="{{ request('date_to') }}">
                </div>

                <div class="col-12 mt-2 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary btn-sm mr-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-funnel mr-1" viewBox="0 0 16 16">
                            <path d="M1.5 1.5A.5.5 0 0 1 2 1h12a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.128.334L10 8.692V13.5a.5.5 0 0 1-.342.474l-3 1A.5.5 0 0 1 6 14.5V8.692L1.628 3.834A.5.5 0 0 1 1.5 3.5v-2z"/>
                        </svg>
                        {{ __('Aplicar Filtros') }}
                    </button>
                    @if (request()->hasAny(['search', 'client', 'status', 'date_from', 'date_to']))
                        <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary btn-sm">
                            {{ __('Limpiar Filtros') }}
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Tabla de Órdenes --}}
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            @if ($orders->isEmpty())
                <div class="text-center py-5">
                    <h5 class="text-muted font-weight-bold">{{ __('No se encontraron pedidos.') }}</h5>
                    <p class="text-muted small mb-0">{{ __('No hay pedidos que coincidan con los criterios de búsqueda.') }}</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ __('Ref / ID') }}</th>
                                <th>{{ __('Cliente') }}</th>
                                <th>{{ __('Fecha') }}</th>
                                <th class="text-center">{{ __('Artículos') }}</th>
                                <th class="text-right">{{ __('Total') }}</th>
                                <th class="text-center">{{ __('Estado') }}</th>
                                <th class="text-center" style="width: 200px;">{{ __('Acciones') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $order)
                                <tr>
                                    <td class="font-weight-bold text-dark align-middle">
                                        #{{ $order->reference }}
                                        <div class="small text-muted font-weight-normal">ID: {{ $order->id }}</div>
                                    </td>
                                    <td class="align-middle">
                                        <strong>{{ $order->customer_name ?: ($order->user ? $order->user->name . ' ' . $order->user->last_Name : __('Sin nombre')) }}</strong>
                                        <div class="small text-muted">{{ $order->customer_email ?: ($order->user ? $order->user->email : '-') }}</div>
                                        @if ($order->user_identification)
                                            <div class="small text-secondary">CC: {{ $order->user_identification }}</div>
                                        @endif
                                    </td>
                                    <td class="text-muted align-middle">
                                        {{ $order->created_at ? $order->created_at->format('d/m/Y H:i') : '-' }}
                                    </td>
                                    <td class="text-center align-middle">
                                        <span class="badge badge-light border">
                                            {{ $order->getTotalQuantity() }} {{ __('unds') }} ({{ $order->items->count() }})
                                        </span>
                                    </td>
                                    <td class="text-right font-weight-bold text-dark align-middle">
                                        ${{ number_format($order->total_amount, 2, ',', '.') }} {{ $order->currency }}
                                    </td>
                                    <td class="text-center align-middle">
                                        @if ($order->status === 'approved')
                                            <span class="badge badge-success px-2 py-1">{{ __('Aprobado') }}</span>
                                        @elseif ($order->status === 'pending_payment')
                                            <span class="badge badge-warning px-2 py-1 text-dark">{{ __('Pendiente Pago') }}</span>
                                        @elseif ($order->status === 'rejected')
                                            <span class="badge badge-danger px-2 py-1">{{ __('Rechazado') }}</span>
                                        @elseif ($order->status === 'cancelled')
                                            <span class="badge badge-secondary px-2 py-1">{{ __('Cancelado') }}</span>
                                        @elseif ($order->status === 'in_cart')
                                            <span class="badge badge-info px-2 py-1">{{ __('En Carrito') }}</span>
                                        @else
                                            <span class="badge badge-dark px-2 py-1">{{ $order->status }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center align-middle">
                                        <div class="d-flex justify-content-center align-items-center">
                                            <a href="{{ route('admin.orders.show', $order->id) }}" class="btn btn-outline-primary btn-sm mr-2" title="{{ __('Ver detalle y gestionar') }}">
                                                {{ __('Detalle') }}
                                            </a>

                                            {{-- Cambio Rápido de Estado --}}
                                            <form action="{{ route('admin.orders.update-status', $order->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <select name="status" class="form-control form-control-sm custom-select custom-select-sm d-inline-block" style="width: auto;" onchange="if(confirm('¿Deseas cambiar el estado a ' + this.options[this.selectedIndex].text + '?')) { this.form.submit(); } else { this.value = '{{ $order->status }}'; }">
                                                    <option value="pending_payment" {{ $order->status === 'pending_payment' ? 'selected' : '' }}>Pendiente</option>
                                                    <option value="approved" {{ $order->status === 'approved' ? 'selected' : '' }}>Aprobar</option>
                                                    <option value="rejected" {{ $order->status === 'rejected' ? 'selected' : '' }}>Rechazar</option>
                                                    <option value="cancelled" {{ $order->status === 'cancelled' ? 'selected' : '' }}>Cancelar</option>
                                                </select>
                                            </form>
                                        </div>
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
