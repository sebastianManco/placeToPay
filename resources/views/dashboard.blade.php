@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
        <div>
            <h1 class="h2 font-weight-bold mb-1">{{ $title ?? __('Vitrina de Productos') }}</h1>
            <p class="text-muted mb-0">{{ __('Explora nuestros productos disponibles') }}</p>
        </div>
        <div class="mt-2 mt-md-0">
            <span class="badge badge-primary px-3 py-2 font-weight-normal" style="font-size: 0.95rem;">
                {{ __('Total disponibles:') }} {{ $products->total() }}
            </span>
        </div>
    </div>

    {{-- Filtros y Búsqueda --}}
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body bg-light rounded">
            <form method="GET" action="{{ route('dashboard') }}" class="form-row align-items-center">
                <div class="col-md-5 my-1">
                    <div class="input-group">
                        <input type="text"
                               name="search"
                               class="form-control"
                               placeholder="Buscar producto por nombre o descripción..."
                               value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-4 my-1">
                    <select name="category" class="custom-select">
                        <option value="">-- Todas las categorías --</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 my-1 d-flex">
                    <button type="submit" class="btn btn-primary flex-grow-1 mr-2">
                        {{ __('Filtrar') }}
                    </button>
                    @if(request('search') || request('category'))
                        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
                            {{ __('Limpiar') }}
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Cuadrícula de Productos (Vitrina) --}}
    @if ($products->count() > 0)
        <div class="row">
            @foreach ($products as $product)
                <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-4 d-flex align-items-stretch">
                    <div class="card h-100 shadow-sm border-0 w-100 product-card" style="transition: transform 0.2s, box-shadow 0.2s;">
                        {{-- Imagen del producto o Placeholder --}}
                        <div class="position-relative bg-white text-center rounded-top overflow-hidden" style="height: 220px;">
                            @if ($product->image)
                                <img src="{{ asset('storage/' . $product->image) }}"
                                     class="card-img-top w-100 h-100"
                                     style="object-fit: cover;"
                                     alt="{{ $product->name }}">
                            @else
                                <div class="w-100 h-100 d-flex flex-column justify-content-center align-items-center bg-light text-muted">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" class="bi bi-card-image mb-2 text-secondary" viewBox="0 0 16 16">
                                        <path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/>
                                        <path d="M1.5 2A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2h-13zm13 1a.5.5 0 0 1 .5.5v6l-3.775-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12V3.5a.5.5 0 0 1 .5-.5h13z"/>
                                    </svg>
                                    <span class="small">{{ __('Sin imagen') }}</span>
                                </div>
                            @endif

                            @if ($product->category)
                                <span class="badge badge-info position-absolute" style="top: 10px; right: 10px; font-size: 0.8rem; z-index: 1;">
                                    {{ $product->category->name }}
                                </span>
                            @endif
                        </div>

                        {{-- Contenido de la Card --}}
                        <div class="card-body d-flex flex-column justify-content-between p-3">
                            <div>
                                <h5 class="card-title font-weight-bold mb-1 text-dark" title="{{ $product->name }}" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    {{ $product->name }}
                                </h5>
                                <p class="card-text text-muted small mb-3" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.4rem;">
                                    {{ $product->description ?: __('Sin descripción detallada.') }}
                                </p>
                            </div>

                            <div>
                                <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                    <div>
                                        <div class="small text-muted">{{ __('Precio') }}</div>
                                        <span class="h5 mb-0 font-weight-bold text-primary">
                                            ${{ number_format($product->price, 2, ',', '.') }}
                                        </span>
                                    </div>
                                    <div class="text-right">
                                        @if ($product->stock > 0)
                                            <span class="badge badge-pill badge-success">{{ __('Stock:') }} {{ $product->stock }}</span>
                                        @else
                                            <span class="badge badge-pill badge-secondary">{{ __('Agotado') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Paginación --}}
        @if ($products->hasPages())
            <div class="d-flex justify-content-center mt-3 mb-5">
                {{ $products->links() }}
            </div>
        @endif
    @else
        <div class="card shadow-sm border-0 py-5 my-4 text-center">
            <div class="card-body">
                <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" class="bi bi-box-seam text-muted mb-3" viewBox="0 0 16 16">
                    <path d="M8.186 1.113a.5.5 0 0 0-.372 0L1.846 3.5l2.404.961L10.404 2l-2.218-.887zm3.564 1.426L5.596 5 8 5.961 14.154 3.5l-2.404-.961zm3.25 1.7-6.5 2.6v7.922l6.5-2.6V4.24zM7.5 14.762V6.838L1 4.239v7.923l6.5 2.6zM7.443.184a1.5 1.5 0 0 1 1.114 0l7.129 2.852A.5.5 0 0 1 16 3.5v8.662a1 1 0 0 1-.629.928l-7.185 2.874a.5.5 0 0 1-.372 0L.63 13.09a1 1 0 0 1-.63-.928V3.5a.5.5 0 0 1 .314-.464L7.443.184z"/>
                </svg>
                <h4 class="text-muted font-weight-bold">{{ __('No se encontraron productos') }}</h4>
                <p class="text-muted mb-3">
                    @if(request('search') || request('category'))
                        {{ __('No hay productos que coincidan con los filtros de búsqueda ingresados.') }}
                    @else
                        {{ __('Actualmente no hay productos disponibles en la vitrina.') }}
                    @endif
                </p>
                @if(request('search') || request('category'))
                    <a href="{{ route('dashboard') }}" class="btn btn-outline-primary btn-sm">
                        {{ __('Ver todos los productos') }}
                    </a>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
