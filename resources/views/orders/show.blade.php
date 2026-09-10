@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">{{ __('Detalle de la Orden') }} #{{ $order->reference }}</h1>
            <p class="text-muted small mb-0">{{ __('Fecha:') }} {{ $order->created_at ? $order->created_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}</p>
        </div>
        <div>
            @auth
                <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary btn-sm me-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-clock-history me-1" viewBox="0 0 16 16">
                        <path d="M8.515 1.019A7 7 0 0 0 8 1V0a8 8 0 0 1 .589.022l-.074.997zm2.004.45a7.003 7.003 0 0 0-.985-.299l.219-.976c.383.086.76.2 1.126.342l-.36.933zm1.37.71a7.01 7.01 0 0 0-.439-.27l.493-.87a8.025 8.025 0 0 1 .974.606l-.552.834a6.974 6.974 0 0 0-.476-.3z"/>
                        <path d="M7 3v5.2l4 2.4.8-1.3L8.5 7.2V3H7z"/>
                        <path d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm8-7a7 7 0 1 0 0 14A7 7 0 0 0 8 1z"/>
                    </svg>
                    {{ __('Mis Pedidos') }}
                </a>
            @endauth
            <a href="{{ route('showcase.index') }}" class="btn btn-outline-primary btn-sm">
                {{ __('Volver a la Tienda') }}
            </a>
        </div>
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

    @if (session('info'))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            {{ session('info') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($order->status === 'refund_pending')
        <div class="alert alert-warning border-0 shadow-sm mb-4" role="alert">
            <h5 class="alert-heading fw-bold mb-1">Pago Recibido - Incidencia de Inventario en Proceso</h5>
            <p class="mb-0 small">Tu transacción fue recibida por la pasarela de pagos, pero debido a agotamiento simultáneo de stock no fue posible despachar la orden inmediatamente. Nuestro equipo de atención al cliente ya fue notificado con máxima prioridad para gestionar tu entrega prioritaria o procesar tu reembolso.</p>
        </div>
    @elseif ($order->status === 'reversed')
        <div class="alert alert-secondary border-0 shadow-sm mb-4" role="alert">
            <h5 class="alert-heading fw-bold mb-1">Pago Revertido</h5>
            <p class="mb-0 small">El cobro fue revertido automáticamente por la pasarela de pagos debido a falta de existencias de inventario. Los fondos han sido devueltos a tu medio de pago original.</p>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
                    <span>{{ __('Resumen de Productos') }}</span>
                    @if ($order->status === 'approved')
                        <span class="badge bg-success px-3 py-2 text-uppercase" style="font-size: 0.85rem;">{{ __('Aprobada') }}</span>
                    @elseif ($order->status === 'pending_payment')
                        <span class="badge bg-warning px-3 py-2 text-uppercase text-dark" style="font-size: 0.85rem;">{{ __('Pendiente de Pago') }}</span>
                    @elseif ($order->status === 'rejected')
                        <span class="badge bg-danger px-3 py-2 text-uppercase" style="font-size: 0.85rem;">{{ __('Rechazada') }}</span>
                    @elseif ($order->status === 'cancelled')
                        <span class="badge bg-secondary px-3 py-2 text-uppercase" style="font-size: 0.85rem;">{{ __('Cancelada') }}</span>
                    @elseif ($order->status === 'refund_pending')
                        <span class="badge bg-warning text-dark px-3 py-2 text-uppercase" style="font-size: 0.85rem;">{{ __('Reembolso Pendiente') }}</span>
                    @elseif ($order->status === 'reversed')
                        <span class="badge bg-dark px-3 py-2 text-uppercase" style="font-size: 0.85rem;">{{ __('Revertida') }}</span>
                    @else
                        <span class="badge bg-info text-dark px-3 py-2 text-uppercase" style="font-size: 0.85rem;">{{ $order->status }}</span>
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Producto') }}</th>
                                <th class="text-center" style="width: 140px;">{{ __('Precio Unitario') }}</th>
                                <th class="text-center" style="width: 100px;">{{ __('Cantidad') }}</th>
                                <th class="text-end" style="width: 140px;">{{ __('Subtotal') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->items as $item)
                                <tr>
                                    <td class="fw-bold text-dark">{{ $item->product_name }}</td>
                                    <td class="text-center text-muted">${{ number_format($item->unit_price, 2, ',', '.') }}</td>
                                    <td class="text-center fw-bold">{{ $item->quantity }}</td>
                                    <td class="text-end fw-bold text-dark">${{ number_format($item->subtotal, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-light">
                            <tr>
                                <th colspan="3" class="text-end h5 mb-0">{{ __('Total:') }}</th>
                                <th class="text-end h5 mb-0 fw-bold text-primary">
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
                <div class="card-header bg-white fw-bold py-3">
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
                    @if ($order->isApproved())
                        <div class="alert alert-success small mb-0">
                            <strong>{{ __('Pago Aprobado') }}</strong>
                            <p class="mb-0 mt-1">{{ __('La transacción ha sido aprobada exitosamente por PlaceToPay.') }}</p>
                            @if ($order->request_id)
                                <small class="text-muted d-block mt-1">{{ __('ID de Sesión:') }} {{ $order->request_id }}</small>
                            @endif
                        </div>
                    @elseif ($order->canBePaid())
                        @if ($order->isRejected())
                            <div class="alert alert-danger small mb-3">
                                <strong>{{ __('Pago no satisfactorio') }}</strong>
                                <p class="mb-0 mt-1">{{ __('La transacción previa fue rechazada o cancelada. Puedes reintentar el pago con PlaceToPay a continuación sin perder los productos de tu orden.') }}</p>
                            </div>
                        @endif
                        <div class="p-3 bg-light rounded border">
                            <div class="d-flex align-items-center mb-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-shield-check text-primary me-2" viewBox="0 0 16 16">
                                    <path d="M5.338 1.59a61.44 61.44 0 0 0-2.837.856.481.481 0 0 0-.328.39c-.554 4.157.726 7.19 2.253 9.188a10.725 10.725 0 0 0 2.287 2.233c.346.244.652.42.893.533.12.057.218.095.293.118a.55.55 0 0 0 .101.025.615.615 0 0 0 .1-.025c.076-.023.174-.061.294-.118.24-.113.547-.29.893-.533a10.726 10.726 0 0 0 2.287-2.233c1.527-1.997 2.807-5.031 2.253-9.188a.48.48 0 0 0-.328-.39c-.651-.213-1.75-.56-2.837-.855C9.552 1.29 8.531 1.067 8 1.067c-.53 0-1.552.223-2.662.524zM5.072.56C6.157.265 7.31 0 8 0s1.843.265 2.928.56c1.11.3 2.229.655 2.887.87a1.54 1.54 0 0 1 1.044 1.262c.599 4.491-.71 7.776-2.383 9.992a12.727 12.727 0 0 1-2.73 2.68 1.772 1.772 0 0 1-.944.296 1.772 1.772 0 0 1-.944-.296 12.727 12.727 0 0 1-2.73-2.68C2.38 12.607 1.07 9.322 1.67 4.83a1.54 1.54 0 0 1 1.044-1.262c.658-.215 1.777-.57 2.887-.87z"/>
                                    <path d="M10.854 5.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 7.793l2.646-2.647a.5.5 0 0 1 .708 0z"/>
                                </svg>
                                <strong class="text-dark">{{ __('Pasarela de Pagos PlaceToPay') }}</strong>
                            </div>
                            <p class="small text-muted mb-3">
                                {{ __('Haz clic para ser redirigido a la pasarela segura de PlaceToPay y realizar el pago.') }}
                            </p>
                            <form action="{{ route('payment.pay', $order->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm py-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-credit-card me-1" viewBox="0 0 16 16">
                                        <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4zm2-1a1 1 0 0 0-1 1v1h14V4a1 1 0 0 0-1-1H2zm13 4H1v5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7z"/>
                                        <path d="M2 10a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-1z"/>
                                    </svg>
                                    {{ $order->isRejected() ? __('Reintentar Pago con PlaceToPay') : __('Pagar con PlaceToPay') }}
                                </button>
                            </form>
                            @if ($order->process_url)
                                <a href="{{ $order->process_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary w-100 btn-sm mt-2">
                                    {{ __('Continuar sesión de pago abierta') }} &rarr;
                                </a>
                            @endif
                        </div>
                    @else
                        <div class="alert alert-secondary small mb-0">
                            <strong>{{ __('Estado de la Orden:') }}</strong> {{ $order->status }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
