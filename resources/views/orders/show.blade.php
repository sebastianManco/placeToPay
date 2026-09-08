@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 font-weight-bold text-dark mb-1">{{ __('Detalle de la Orden') }} #{{ $order->reference }}</h1>
            <p class="text-muted small mb-0">{{ __('Fecha:') }} {{ $order->created_at ? $order->created_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}</p>
        </div>
        <a href="{{ route('showcase.index') }}" class="btn btn-outline-primary btn-sm">
            {{ __('Volver a la Tienda') }}
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

    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white font-weight-bold py-3 d-flex justify-content-between align-items-center">
                    <span>{{ __('Resumen de Productos') }}</span>
                    <span class="badge badge-warning px-3 py-2 text-uppercase" style="font-size: 0.85rem;">
                        {{ $order->status === 'pending_payment' ? __('Pendiente de Pago') : $order->status }}
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ __('Producto') }}</th>
                                <th class="text-center" style="width: 140px;">{{ __('Precio Unitario') }}</th>
                                <th class="text-center" style="width: 100px;">{{ __('Cantidad') }}</th>
                                <th class="text-right" style="width: 140px;">{{ __('Subtotal') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->items as $item)
                                <tr>
                                    <td class="font-weight-bold text-dark">{{ $item->product_name }}</td>
                                    <td class="text-center text-muted">${{ number_format($item->unit_price, 2, ',', '.') }}</td>
                                    <td class="text-center font-weight-bold">{{ $item->quantity }}</td>
                                    <td class="text-right font-weight-bold text-dark">${{ number_format($item->subtotal, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-light">
                            <tr>
                                <th colspan="3" class="text-right h5 mb-0">{{ __('Total:') }}</th>
                                <th class="text-right h5 mb-0 font-weight-bold text-primary">
                                    ${{ number_format($order->total_amount, 2, ',', '.') }} {{ $order->currency }}
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white font-weight-bold py-3">
                    {{ __('Información del Comprador') }}
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <span class="text-muted small d-block">{{ __('Nombre:') }}</span>
                        <strong>{{ $order->customer_name ?: __('No especificado') }}</strong>
                    </div>
                    <div class="mb-2">
                        <span class="text-muted small d-block">{{ __('Correo electrónico:') }}</span>
                        <strong>{{ $order->customer_email ?: __('No especificado') }}</strong>
                    </div>
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
                    <hr>
                    <div class="alert alert-info small mb-0">
                        <strong>{{ __('Pasarela de Pagos:') }}</strong>
                        {{ __('La orden se encuentra registrada con estado Pendiente de Pago, lista para su integración con PlaceToPay.') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
