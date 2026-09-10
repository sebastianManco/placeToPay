@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
        <div>
            <h1 class="h2 fw-bold mb-1">{{ $title ?? __('Vitrina de Productos') }}</h1>
            <p class="text-muted mb-0">{{ __('Explora nuestros productos disponibles') }}</p>
        </div>
        <div class="mt-2 mt-md-0">
            <span class="badge bg-primary px-3 py-2 fw-normal" style="font-size: 0.95rem;">
                {{ __('Total disponibles:') }} {{ $products->total() }}
            </span>
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

    {{-- Filtros y Búsqueda Personalizada --}}
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-white border-0 pb-0 pt-3">
            <h5 class="fw-bold text-dark mb-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-sliders me-2 text-primary" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M11.5 2a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zM9.05 3a2.5 2.5 0 0 1 4.9 0H16v1h-2.05a2.5 2.5 0 0 1-4.9 0H0V3h9.05zM4.5 7a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zM2.05 8a2.5 2.5 0 0 1 4.9 0H16v1H6.95a2.5 2.5 0 0 1-4.9 0H0V8h2.05zm9.45 4a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm-2.45 1a2.5 2.5 0 0 1 4.9 0H16v1h-2.05a2.5 2.5 0 0 1-4.9 0H0v-1h9.05z"/>
                </svg>
                {{ __('Búsqueda Personalizada') }}
            </h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ url()->current() }}" id="search-form">
                <div class="row">
                    {{-- Búsqueda por texto --}}
                    <div class="col-12 col-md-6 mb-3">
                        <label for="search" class="small fw-bold text-muted mb-1">{{ __('Palabra clave') }}</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-search text-muted" viewBox="0 0 16 16">
                                    <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                                </svg>
                            </span>
                            <input type="text"
                                   id="search"
                                   name="search"
                                   class="form-control border-start-0"
                                   placeholder="{{ __('Buscar producto por nombre o descripción...') }}"
                                   value="{{ request('search') }}">
                        </div>
                    </div>

                    {{-- Filtro por categoría --}}
                    <div class="col-12 col-md-6 mb-3">
                        <label for="category" class="small fw-bold text-muted mb-1">{{ __('Categoría') }}</label>
                        <select id="category" name="category" class="form-select">
                            <option value="">{{ __('-- Todas las categorías --') }}</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>
                                    {{ $cat->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Precio Mínimo --}}
                    <div class="col-6 col-md-3 mb-3">
                        <label for="min_price" class="small fw-bold text-muted mb-1">{{ __('Precio mín. ($)') }}</label>
                        <input type="number"
                               id="min_price"
                               name="min_price"
                               step="0.01"
                               min="0"
                               class="form-control"
                               placeholder="{{ __('0.00') }}"
                               value="{{ request('min_price') }}">
                    </div>

                    {{-- Precio Máximo --}}
                    <div class="col-6 col-md-3 mb-3">
                        <label for="max_price" class="small fw-bold text-muted mb-1">{{ __('Precio máx. ($)') }}</label>
                        <input type="number"
                               id="max_price"
                               name="max_price"
                               step="0.01"
                               min="0"
                               class="form-control"
                               placeholder="{{ __('Sin límite') }}"
                               value="{{ request('max_price') }}">
                    </div>

                    {{-- Ordenamiento --}}
                    <div class="col-12 col-md-3 mb-3">
                        <label for="sort_by" class="small fw-bold text-muted mb-1">{{ __('Ordenar por') }}</label>
                        <select id="sort_by" name="sort_by" class="form-select">
                            <option value="name_asc" {{ request('sort_by') == 'name_asc' ? 'selected' : '' }}>{{ __('Nombre (A - Z)') }}</option>
                            <option value="name_desc" {{ request('sort_by') == 'name_desc' ? 'selected' : '' }}>{{ __('Nombre (Z - A)') }}</option>
                            <option value="price_asc" {{ request('sort_by') == 'price_asc' ? 'selected' : '' }}>{{ __('Precio (Menor a Mayor)') }}</option>
                            <option value="price_desc" {{ request('sort_by') == 'price_desc' ? 'selected' : '' }}>{{ __('Precio (Mayor a Menor)') }}</option>
                            <option value="newest" {{ request('sort_by') == 'newest' ? 'selected' : '' }}>{{ __('Más recientes') }}</option>
                        </select>
                    </div>

                    {{-- Solo en stock --}}
                    <div class="col-12 col-md-3 mb-3 d-flex align-items-center">
                        <div class="form-check mt-md-3">
                            <input type="checkbox"
                                   class="form-check-input"
                                   id="in_stock"
                                   name="in_stock"
                                   value="1"
                                   {{ request('in_stock') ? 'checked' : '' }}>
                            <label class="form-check-label small fw-bold text-secondary" for="in_stock">
                                {{ __('Solo con stock disponible') }}
                            </label>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end align-items-center mt-2 flex-wrap">
                    @if(request()->filled('search') || request()->filled('category') || request()->filled('min_price') || request()->filled('max_price') || request()->filled('in_stock') || (request()->filled('sort_by') && request('sort_by') !== 'name_asc'))
                        <a href="{{ url()->current() }}" class="btn btn-outline-secondary me-2 my-1">
                            {{ __('Limpiar') }}
                        </a>
                    @endif
                    <button type="submit" class="btn btn-primary px-4 my-1">
                        {{ __('Filtrar') }}
                    </button>
                </div>
            </form>

            {{-- Resumen de Filtros Activos --}}
            @php
                $hasActiveFilters = request()->filled('search') || request()->filled('category') || request()->filled('min_price') || request()->filled('max_price') || request()->filled('in_stock') || (request()->filled('sort_by') && request('sort_by') !== 'name_asc');
            @endphp
            @if($hasActiveFilters)
                <div class="mt-3 pt-3 border-top d-flex align-items-center flex-wrap" style="gap: 0.4rem;">
                    <span class="small fw-bold text-muted me-2">{{ __('Filtros aplicados:') }}</span>
                    @if(request()->filled('search'))
                        <span class="badge bg-light border text-dark px-2 py-1">
                            {{ __('Texto:') }} "{{ request('search') }}"
                        </span>
                    @endif
                    @if(request()->filled('category'))
                        @php
                            $activeCategory = $categories->firstWhere('id', request('category'));
                        @endphp
                        @if($activeCategory)
                            <span class="badge bg-light border text-dark px-2 py-1">
                                {{ __('Categoría:') }} {{ $activeCategory->name }}
                            </span>
                        @endif
                    @endif
                    @if(request()->filled('min_price'))
                        <span class="badge bg-light border text-dark px-2 py-1">
                            {{ __('Mín:') }} ${{ number_format((float) request('min_price'), 2, ',', '.') }}
                        </span>
                    @endif
                    @if(request()->filled('max_price'))
                        <span class="badge bg-light border text-dark px-2 py-1">
                            {{ __('Máx:') }} ${{ number_format((float) request('max_price'), 2, ',', '.') }}
                        </span>
                    @endif
                    @if(request()->filled('in_stock'))
                        <span class="badge bg-success px-2 py-1">
                            {{ __('Solo disponibles') }}
                        </span>
                    @endif
                    @if(request()->filled('sort_by') && request('sort_by') !== 'name_asc')
                        <span class="badge bg-info text-dark px-2 py-1">
                            {{ __('Orden:') }} 
                            @if(request('sort_by') === 'name_desc') {{ __('Z - A') }}
                            @elseif(request('sort_by') === 'price_asc') {{ __('Menor precio') }}
                            @elseif(request('sort_by') === 'price_desc') {{ __('Mayor precio') }}
                            @elseif(request('sort_by') === 'newest') {{ __('Más recientes') }}
                            @endif
                        </span>
                    @endif
                </div>
            @endif
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
                                <span class="badge bg-info text-dark position-absolute" style="top: 10px; right: 10px; font-size: 0.8rem; z-index: 1;">
                                    {{ $product->category->name }}
                                </span>
                            @endif
                        </div>

                        {{-- Contenido de la Card --}}
                        <div class="card-body d-flex flex-column justify-content-between p-3">
                            <div>
                                <h5 class="card-title fw-bold mb-1 text-dark" title="{{ $product->name }}" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
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
                                        <span class="h5 mb-0 fw-bold text-primary">
                                            ${{ number_format($product->price, 2, ',', '.') }}
                                        </span>
                                    </div>
                                    <div class="text-end">
                                        @if ($product->stock > 0)
                                            <span class="badge rounded-pill bg-success">{{ __('Stock:') }} {{ $product->stock }}</span>
                                        @else
                                            <span class="badge rounded-pill bg-secondary">{{ __('Agotado') }}</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Botón Añadir al carrito --}}
                                <div class="mt-3">
                                    @if ($product->stock > 0)
                                        <form action="{{ route('cart.items.store') }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                                            <input type="hidden" name="quantity" value="1">
                                            <button type="submit" class="btn btn-outline-primary btn-sm w-100 d-flex align-items-center justify-content-center" data-product-id="{{ $product->id }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" class="bi bi-cart-plus me-1" viewBox="0 0 16 16">
                                                    <path d="M9 5.5a.5.5 0 0 0-1 0V7H6.5a.5.5 0 0 0 0 1H8v1.5a.5.5 0 0 0 1 0V8h1.5a.5.5 0 0 0 0-1H9V5.5z"/>
                                                    <path d="M.5 1a.5.5 0 0 0 0 1h1.11l.401 1.607 1.498 7.985A.5.5 0 0 0 4 12h1a2 2 0 1 0 0 4 2 2 0 0 0 0-4h7a2 2 0 1 0 0 4 2 2 0 0 0 0-4h1a.5.5 0 0 0 .491-.408l1.5-8A.5.5 0 0 0 14.5 3H2.89l-.405-1.621A.5.5 0 0 0 2 1H.5zm3.915 10L3.102 4h10.796l-1.313 7h-8.17zM6 14a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm7 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/>
                                                </svg>
                                                {{ __('Añadir al carrito') }}
                                            </button>
                                        </form>
                                    @else
                                        <button type="button" class="btn btn-outline-secondary btn-sm w-100 disabled" disabled>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" class="bi bi-dash-circle me-1" viewBox="0 0 16 16">
                                                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                                                <path d="M4 8a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7A.5.5 0 0 1 4 8z"/>
                                            </svg>
                                            {{ __('Agotado') }}
                                        </button>
                                    @endif
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
                <h4 class="text-muted fw-bold">{{ __('No se encontraron productos') }}</h4>
                <p class="text-muted mb-3">
                    @if(request()->filled('search') || request()->filled('category') || request()->filled('min_price') || request()->filled('max_price') || request()->filled('in_stock'))
                        {{ __('No hay productos que coincidan con los filtros de búsqueda ingresados.') }}
                    @else
                        {{ __('Actualmente no hay productos disponibles en la vitrina.') }}
                    @endif
                </p>
                @if(request()->filled('search') || request()->filled('category') || request()->filled('min_price') || request()->filled('max_price') || request()->filled('in_stock'))
                    <a href="{{ url()->current() }}" class="btn btn-outline-primary btn-sm">
                        {{ __('Ver todos los productos') }}
                    </a>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
