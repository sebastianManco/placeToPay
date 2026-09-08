@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 font-weight-bold text-dark mb-1">{{ __('Carrito de Compras') }}</h1>
            <p class="text-muted small mb-0">{{ __('Revisa y modifica los productos de tu pedido antes de proceder con el pago.') }}</p>
        </div>
        <a href="{{ route('showcase.index') }}" class="btn btn-outline-primary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left mr-1" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
            </svg>
            {{ __('Seguir Comprando') }}
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

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if ($cart->items->isEmpty())
        <div class="card shadow-sm border-0 text-center py-5">
            <div class="card-body">
                <div class="mb-3 text-muted">
                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" class="bi bi-cart-x text-secondary" viewBox="0 0 16 16">
                        <path d="M7.354 5.646a.5.5 0 1 0-.708.708L7.793 7.5 6.646 8.646a.5.5 0 1 0 .708.708L8.5 8.207l1.146 1.147a.5.5 0 0 0 .708-.708L9.207 7.5l1.147-1.146a.5.5 0 0 0-.708-.708L8.5 6.793 7.354 5.646z"/>
                        <path d="M.5 1a.5.5 0 0 0 0 1h1.11l.401 1.607 1.498 7.985A.5.5 0 0 0 4 12h1a2 2 0 1 0 0 4 2 2 0 0 0 0-4h7a2 2 0 1 0 0 4 2 2 0 0 0 0-4h1a.5.5 0 0 0 .491-.408l1.5-8A.5.5 0 0 0 14.5 3H2.89l-.405-1.621A.5.5 0 0 0 2 1H.5zm3.915 10L3.102 4h10.796l-1.313 7h-8.17zM6 14a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm7 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/>
                    </svg>
                </div>
                <h4 class="font-weight-bold text-dark mb-2">{{ __('Tu carrito está vacío') }}</h4>
                <p class="text-muted mb-4">{{ __('Parece que aún no has agregado productos a tu carrito.') }}</p>
                <a href="{{ route('showcase.index') }}" class="btn btn-primary px-4">
                    {{ __('Explorar Catálogo de Productos') }}
                </a>
            </div>
        </div>
    @else
        <div class="row">
            {{-- Listado de Ítems --}}
            <div class="col-lg-8 mb-4">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white font-weight-bold py-3">
                        {{ __('Productos Seleccionados') }} ({{ $cart->items->count() }})
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>{{ __('Producto') }}</th>
                                    <th class="text-center" style="width: 120px;">{{ __('Precio Unitario') }}</th>
                                    <th class="text-center" style="width: 160px;">{{ __('Cantidad') }}</th>
                                    <th class="text-right" style="width: 130px;">{{ __('Subtotal') }}</th>
                                    <th class="text-center" style="width: 80px;">{{ __('Acción') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cart->items as $item)
                                    <tr>
                                        <td>
                                            <div class="font-weight-bold text-dark">{{ $item->product_name }}</div>
                                            @if ($item->product)
                                                <small class="text-muted">
                                                    {{ __('Stock disponible:') }} {{ $item->product->stock }}
                                                </small>
                                            @endif
                                        </td>
                                        <td class="text-center font-weight-bold text-secondary">
                                            ${{ number_format($item->unit_price, 2, ',', '.') }}
                                        </td>
                                        <td>
                                            <form action="{{ route('cart.items.update', $item->id) }}" method="POST" class="d-flex align-items-center justify-content-center">
                                                @csrf
                                                @method('PUT')
                                                <input type="number" 
                                                       name="quantity" 
                                                       value="{{ $item->quantity }}" 
                                                       min="1" 
                                                       max="{{ $item->product ? $item->product->stock : 999 }}" 
                                                       class="form-control form-control-sm text-center mr-2" 
                                                       style="width: 70px;" 
                                                       required>
                                                <button type="submit" class="btn btn-outline-secondary btn-sm" title="{{ __('Actualizar cantidad') }}">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-arrow-repeat" viewBox="0 0 16 16">
                                                        <path d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41zm-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9z"/>
                                                        <path fill-rule="evenodd" d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.9A5.002 5.002 0 0 0 8 3zM3.1 9a5.002 5.002 0 0 0 8.757 2.182.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9H3.1z"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        </td>
                                        <td class="text-right font-weight-bold text-dark">
                                            ${{ number_format($item->subtotal, 2, ',', '.') }}
                                        </td>
                                        <td class="text-center">
                                            <form action="{{ route('cart.items.destroy', $item->id) }}" method="POST" onsubmit="return confirm('¿Estás seguro de quitar este producto?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm" title="{{ __('Eliminar producto') }}">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16">
                                                        <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                                                        <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
                        <form action="{{ route('cart.clear') }}" method="POST" onsubmit="return confirm('¿Deseas vaciar todo el carrito?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                {{ __('Vaciar Carrito') }}
                            </button>
                        </form>
                        <span class="small text-muted">
                            {{ __('Moneda:') }} {{ $cart->currency }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Resumen del Pedido --}}
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white font-weight-bold py-3">
                        {{ __('Resumen del Pedido') }}
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">{{ __('Total de artículos:') }}</span>
                            <span class="font-weight-bold">{{ $cart->getTotalQuantity() }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">{{ __('Subtotal:') }}</span>
                            <span class="font-weight-bold">${{ number_format($cart->total_amount, 2, ',', '.') }}</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <span class="h5 font-weight-bold text-dark mb-0">{{ __('Total:') }}</span>
                            <span class="h4 font-weight-bold text-primary mb-0">
                                ${{ number_format($cart->total_amount, 2, ',', '.') }}
                            </span>
                        </div>

                        <a href="{{ route('cart.checkout') }}" class="btn btn-success btn-block btn-lg font-weight-bold shadow-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-check2-circle mr-1" viewBox="0 0 16 16">
                                <path d="M2.5 8a5.5 5.5 0 0 1 8.25-4.764.5.5 0 0 0 .5-.866A6.5 6.5 0 1 0 14.5 8a.5.5 0 0 0-1 0 5.5 5.5 0 1 1-11 0z"/>
                                <path d="M15.354 3.354a.5.5 0 0 0-.708-.708L8 9.293 5.354 6.646a.5.5 0 1 0-.708.708l3 3a.5.5 0 0 0 .708 0l7-7z"/>
                            </svg>
                            {{ __('Confirmar Pedido') }}
                        </a>
                    </div>
                </div>

                <div class="card border-0 bg-light shadow-sm">
                    <div class="card-body p-3 small text-muted">
                        <div class="d-flex align-items-center mb-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-shield-check text-success mr-2" viewBox="0 0 16 16">
                                <path d="M5.338 1.59a61.44 61.44 0 0 0-2.837.856.481.481 0 0 0-.328.39c-.554 4.157.726 7.19 2.253 9.188a10.725 10.725 0 0 0 2.287 2.233c.346.244.652.42.893.533.12.057.218.095.293.118a.55.55 0 0 0 .101.025.615.615 0 0 0 .1-.025c.076-.023.174-.061.294-.118.24-.113.547-.29.893-.533a10.726 10.726 0 0 0 2.287-2.233c1.527-1.997 2.807-5.031 2.253-9.188a.48.48 0 0 0-.328-.39c-.651-.213-1.75-.56-2.837-.855C9.552 1.29 8.531 1.067 8 1.067c-.53 0-1.552.223-2.662.524zM5.072.56C6.157.265 7.31 0 8 0s1.843.265 2.928.56c1.11.3 2.229.655 2.887.87a1.54 1.54 0 0 1 1.044 1.262c.596 4.477-.787 7.795-2.465 9.99a11.775 11.775 0 0 1-2.517 2.453 7.159 7.159 0 0 1-1.048.625c-.28.132-.581.24-.829.24s-.548-.108-.829-.24a7.158 7.158 0 0 1-1.048-.625 11.777 11.777 0 0 1-2.517-2.453C1.928 10.487.545 7.169 1.141 2.692A1.54 1.54 0 0 1 2.185 1.43 62.456 62.456 0 0 1 5.072.56z"/>
                                <path d="M10.854 5.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 7.793l2.646-2.647a.5.5 0 0 1 .708 0z"/>
                            </svg>
                            <strong>{{ __('Compra 100% Segura') }}</strong>
                        </div>
                        <p class="mb-0">{{ __('Tus pagos se procesarán de manera confiable y segura a través de PlaceToPay.') }}</p>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
