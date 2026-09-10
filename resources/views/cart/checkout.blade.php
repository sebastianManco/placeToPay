@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 font-weight-bold text-dark mb-1">{{ __('Resumen y Confirmación de la Orden') }}</h1>
            <p class="text-muted small mb-0">{{ __('Verifica los datos de tu pedido y completa la información de contacto antes del pago.') }}</p>
        </div>
        <a href="{{ route('cart.index') }}" class="btn btn-outline-secondary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left mr-1" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
            </svg>
            {{ __('Volver al Carrito') }}
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

    <div class="row">
        {{-- Resumen y Modificación de Artículos --}}
        <div class="col-lg-7 mb-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white font-weight-bold py-3 d-flex justify-content-between align-items-center">
                    <span>{{ __('Artículos del Pedido') }} ({{ $cart->items->count() }})</span>
                    <small class="text-muted">{{ __('Puedes ajustar cantidades o remover productos antes de confirmar') }}</small>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @foreach ($cart->items as $item)
                            <li class="list-group-item py-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="font-weight-bold mb-1">{{ $item->product_name }}</h6>
                                        <small class="text-muted">
                                            ${{ number_format($item->unit_price, 2, ',', '.') }} {{ $cart->currency }} c/u
                                        </small>
                                    </div>
                                    <span class="font-weight-bold text-dark h6 mb-0">
                                        ${{ number_format($item->subtotal, 2, ',', '.') }}
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    {{-- Modificar Cantidad --}}
                                    <form action="{{ route('cart.items.update', $item->id) }}" method="POST" class="d-flex align-items-center">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="redirect_to" value="checkout">
                                        <label class="small text-muted mr-2 mb-0">{{ __('Cantidad:') }}</label>
                                        <input type="number" 
                                               name="quantity" 
                                               value="{{ $item->quantity }}" 
                                               min="1" 
                                               max="{{ $item->product->stock ?? 99 }}" 
                                               class="form-control form-control-sm text-center mr-2" 
                                               style="width: 70px;">
                                        <button type="submit" class="btn btn-outline-primary btn-sm" title="{{ __('Actualizar cantidad') }}">
                                            {{ __('Actualizar') }}
                                        </button>
                                    </form>

                                    {{-- Remover Ítem --}}
                                    <form action="{{ route('cart.items.destroy', $item->id) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="redirect_to" value="checkout">
                                        <button type="submit" class="btn btn-link text-danger btn-sm p-0" onclick="return confirm('¿Deseas quitar este producto del pedido?');">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-trash mr-1" viewBox="0 0 16 16">
                                                <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                                                <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                                            </svg>
                                            {{ __('Quitar') }}
                                        </button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="card-footer bg-light d-flex justify-content-between align-items-center py-3">
                    <span class="h5 font-weight-bold mb-0">{{ __('Total a Pagar:') }}</span>
                    <span class="h4 font-weight-bold text-primary mb-0">
                        ${{ number_format($cart->total_amount, 2, ',', '.') }} {{ $cart->currency }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Formulario de Datos del Cliente y Confirmación --}}
        <div class="col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white font-weight-bold py-3">
                    {{ __('Datos de Facturación y Entrega') }}
                </div>
                <div class="card-body">
                    <form action="{{ route('cart.checkout.confirm') }}" method="POST">
                        @csrf

                        @php
                            $user = Auth::user();
                            $defaultName = $user ? $user->name . ' ' . $user->last_name : '';
                            $defaultEmail = $user ? $user->email : '';
                            $defaultPhone = $user ? $user->phone : '';
                            $defaultAddress = $user ? $user->direction : '';
                        @endphp

                        <div class="form-group mb-3">
                            <label for="customer_name" class="font-weight-bold small text-secondary">
                                {{ __('Nombre Completo') }} <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control @error('customer_name') is-invalid @enderror" 
                                   id="customer_name" 
                                   name="customer_name" 
                                   value="{{ old('customer_name', $cart->customer_name ?: $defaultName) }}" 
                                   required>
                            @error('customer_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label for="customer_email" class="font-weight-bold small text-secondary">
                                {{ __('Correo Electrónico') }} <span class="text-danger">*</span>
                            </label>
                            <input type="email" 
                                   class="form-control @error('customer_email') is-invalid @enderror" 
                                   id="customer_email" 
                                   name="customer_email" 
                                   value="{{ old('customer_email', $cart->customer_email ?: $defaultEmail) }}" 
                                   required>
                            @error('customer_email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label for="customer_phone" class="font-weight-bold small text-secondary">
                                {{ __('Teléfono / Celular') }}
                            </label>
                            <input type="text" 
                                   class="form-control @error('customer_phone') is-invalid @enderror" 
                                   id="customer_phone" 
                                   name="customer_phone" 
                                   value="{{ old('customer_phone', $cart->customer_phone ?: $defaultPhone) }}">
                            @error('customer_phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group mb-4">
                            <label for="customer_address" class="font-weight-bold small text-secondary">
                                {{ __('Dirección de Envío') }}
                            </label>
                            <input type="text" 
                                   class="form-control @error('customer_address') is-invalid @enderror" 
                                   id="customer_address" 
                                   name="customer_address" 
                                   value="{{ old('customer_address', $cart->customer_address ?: $defaultAddress) }}">
                            @error('customer_address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-success btn-block btn-lg font-weight-bold shadow-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-credit-card mr-1" viewBox="0 0 16 16">
                                <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4zm2-1a1 1 0 0 0-1 1v1h14V4a1 1 0 0 0-1-1H2zm13 4H1v5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7z"/>
                                <path d="M2 10a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-1z"/>
                            </svg>
                            {{ __('Confirmar y Proceder al Pago') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
