@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">
                {{ __('Detalle de la Orden') }} #{{ $order->reference }}
            </h1>
            <p class="text-muted small mb-0">
                {{ __('Fecha de creación:') }} {{ $order->created_at ? $order->created_at->format('d/m/Y H:i:s') : '-' }}
            </p>
        </div>
        <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left me-1" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
            </svg>
            {{ __('Volver al Listado de Pedidos') }}
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        {{-- Listado de Ítems Comprados --}}
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
                    <span>{{ __('Productos Solicitados') }} ({{ $order->items->count() }})</span>
                    <div>
                        @if ($order->status === 'approved')
                            <span class="badge bg-success px-3 py-2 text-uppercase">{{ __('Aprobada') }}</span>
                        @elseif ($order->status === 'pending_payment')
                            <span class="badge bg-warning px-3 py-2 text-uppercase text-dark">{{ __('Pendiente de Pago') }}</span>
                        @elseif ($order->status === 'rejected')
                            <span class="badge bg-danger px-3 py-2 text-uppercase">{{ __('Rechazada') }}</span>
                        @elseif ($order->status === 'cancelled')
                            <span class="badge bg-secondary px-3 py-2 text-uppercase">{{ __('Cancelada') }}</span>
                        @elseif ($order->status === 'in_cart')
                            <span class="badge bg-info px-3 py-2 text-uppercase text-dark">{{ __('En Carrito') }}</span>
                        @else
                            <span class="badge bg-dark px-3 py-2 text-uppercase">{{ $order->status }}</span>
                        @endif
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Producto') }}</th>
                                <th class="text-center" style="width: 130px;">{{ __('Precio Unit.') }}</th>
                                <th class="text-center" style="width: 90px;">{{ __('Cant.') }}</th>
                                <th class="text-end" style="width: 140px;">{{ __('Subtotal') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->items as $item)
                                <tr>
                                    <td class="align-middle">
                                        <div class="fw-bold text-dark">{{ $item->product_name }}</div>
                                        @if ($item->product)
                                            <small class="text-muted">Stock actual: {{ $item->product->stock }}</small>
                                        @endif
                                    </td>
                                    <td class="text-center text-muted align-middle">
                                        ${{ number_format($item->unit_price, 2, ',', '.') }}
                                    </td>
                                    <td class="text-center fw-bold align-middle">
                                        {{ $item->quantity }}
                                    </td>
                                    <td class="text-end fw-bold text-dark align-middle">
                                        ${{ number_format($item->subtotal, 2, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-light">
                            <tr>
                                <th colspan="3" class="text-end h5 mb-0">{{ __('Total de la Orden:') }}</th>
                                <th class="text-end h5 mb-0 fw-bold text-primary">
                                    ${{ number_format($order->total_amount, 2, ',', '.') }} {{ $order->currency }}
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- Panel Lateral: Estado, Cliente y Datos --}}
        <div class="col-lg-4">
            {{-- Formulario de Cambio de Estado --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white fw-bold py-3">
                    {{ __('Actualizar Estado del Pedido') }}
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.orders.update-status', $order->id) }}" method="POST">
                        @csrf
                        @method('PATCH')

                        <div class="mb-3">
                            <label for="status" class="small fw-bold text-secondary">
                                {{ __('Nuevo Estado:') }}
                            </label>
                            <select name="status" id="status" class="form-select">
                                <option value="pending_payment" {{ $order->status === 'pending_payment' ? 'selected' : '' }}>
                                    {{ __('Pendiente de Pago') }}
                                </option>
                                <option value="approved" {{ $order->status === 'approved' ? 'selected' : '' }}>
                                    {{ __('Aprobado') }}
                                </option>
                                <option value="rejected" {{ $order->status === 'rejected' ? 'selected' : '' }}>
                                    {{ __('Rechazado') }}
                                </option>
                                <option value="cancelled" {{ $order->status === 'cancelled' ? 'selected' : '' }}>
                                    {{ __('Cancelado') }}
                                </option>
                                <option value="in_cart" {{ $order->status === 'in_cart' ? 'selected' : '' }}>
                                    {{ __('En Carrito') }}
                                </option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 fw-bold">
                            {{ __('Guardar Cambio de Estado') }}
                        </button>
                    </form>
                </div>
            </div>

            {{-- Información del Cliente --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white font-weight-bold py-3">
                    {{ __('Información del Cliente') }}
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <span class="text-muted small d-block">{{ __('Nombre:') }}</span>
                        <strong>{{ $order->customer_name ?: ($order->user ? $order->user->name . ' ' . $order->user->last_name : __('No especificado')) }}</strong>
                    </div>
                    <div class="mb-2">
                        <span class="text-muted small d-block">{{ __('Correo electrónico:') }}</span>
                        <strong>{{ $order->customer_email ?: ($order->user ? $order->user->email : __('No especificado')) }}</strong>
                    </div>
                    @if ($order->user_identification)
                        <div class="mb-2">
                            <span class="text-muted small d-block">{{ __('Identificación (CC):') }}</span>
                            <strong>{{ $order->user_identification }}</strong>
                        </div>
                    @endif
                    @if ($order->customer_phone)
                        <div class="mb-2">
                            <span class="text-muted small d-block">{{ __('Teléfono:') }}</span>
                            <strong>{{ $order->customer_phone }}</strong>
                        </div>
                    @endif
                    @if ($order->customer_address)
                        <div class="mb-2">
                            <span class="text-muted small d-block">{{ __('Dirección de entrega:') }}</span>
                            <strong>{{ $order->customer_address }}</strong>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Metadatos Técnicos --}}
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white font-weight-bold py-3">
                    {{ __('Metadatos del Sistema') }}
                </div>
                <div class="card-body small text-muted">
                    <div><strong>ID Interno:</strong> {{ $order->id }}</div>
                    <div><strong>Referencia:</strong> {{ $order->reference }}</div>
                    <div><strong>Moneda:</strong> {{ $order->currency }}</div>
                    @if ($order->session_id)
                        <div><strong>Session ID:</strong> {{ Str::limit($order->session_id, 20) }}</div>
                    @endif
                    <div><strong>Creado:</strong> {{ $order->created_at ? $order->created_at->format('d/m/Y H:i:s') : '-' }}</div>
                    <div><strong>Actualizado:</strong> {{ $order->updated_at ? $order->updated_at->format('d/m/Y H:i:s') : '-' }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
