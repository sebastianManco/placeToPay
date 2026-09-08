@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>{{ __('Administración de Productos') }}</h2>
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-primary fs-6">{{ __('Total Productos: ') }} {{ $products->total() }}</span>
            <a href="{{ route('admin.products.create') }}" class="btn btn-success">
                + {{ __('Crear Producto') }}
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
        </div>
    @endif

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.products.index') }}" class="row g-3">
                <div class="col-md-4">
                    <input type="text"
                           name="search"
                           class="form-control"
                           placeholder="Buscar por nombre o descripción..."
                           value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-select">
                        <option value="">-- Todas las categorías --</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">-- Estado --</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Habilitados</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inhabilitados</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                    @if(request('search') || request('status') || request('category'))
                        <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('ID') }}</th>
                            <th>{{ __('Nombre') }}</th>
                            <th>{{ __('Categoría') }}</th>
                            <th>{{ __('Precio') }}</th>
                            <th>{{ __('Stock') }}</th>
                            <th>{{ __('Estado') }}</th>
                            <th class="text-center">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            <tr>
                                <td><strong>{{ $product->id }}</strong></td>
                                <td>{{ $product->name }}</td>
                                <td>
                                    @if ($product->category)
                                        <span class="badge bg-secondary">{{ $product->category->name }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>${{ number_format($product->price, 2, ',', '.') }}</td>
                                <td>
                                    @if ($product->stock > 0)
                                        <span class="badge bg-info text-dark">{{ $product->stock }}</span>
                                    @else
                                        <span class="badge bg-secondary">Sin stock</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($product->is_active)
                                        <span class="badge bg-success">Habilitado</span>
                                    @else
                                        <span class="badge bg-danger">Inhabilitado</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-2">
                                        <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-outline-primary" title="Editar producto">
                                            Editar
                                        </a>
                                        <form method="POST" action="{{ route('admin.products.toggle-status', $product) }}" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            @if ($product->is_active)
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Inhabilitar producto">
                                                    Inhabilitar
                                                </button>
                                            @else
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Habilitar producto">
                                                    Habilitar
                                                </button>
                                            @endif
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    {{ __('No se encontraron productos registrados.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($products->hasPages())
            <div class="card-footer bg-white">
                {{ $products->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
