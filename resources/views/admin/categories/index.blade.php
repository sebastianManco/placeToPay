@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>{{ __('Administración de Categorías') }}</h2>
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-primary fs-6">{{ __('Total Categorías: ') }} {{ $categories->total() }}</span>
            <a href="{{ route('admin.categories.create') }}" class="btn btn-success">
                + {{ __('Crear Categoría') }}
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
            <form method="GET" action="{{ route('admin.categories.index') }}" class="row g-3">
                <div class="col-md-5">
                    <input type="text"
                           name="search"
                           class="form-control"
                           placeholder="Buscar por nombre..."
                           value="{{ request('search') }}">
                </div>
                <div class="col-md-4">
                    <select name="status" class="form-select">
                        <option value="">-- Todos los estados --</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Habilitadas</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inhabilitadas</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                    @if(request('search') || request('status'))
                        <a href="{{ route('admin.categories.index') }}" class="btn btn-outline-secondary">Limpiar</a>
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
                            <th>{{ __('Descripción') }}</th>
                            <th>{{ __('Productos') }}</th>
                            <th>{{ __('Estado') }}</th>
                            <th class="text-center">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr>
                                <td><strong>{{ $category->id }}</strong></td>
                                <td>{{ $category->name }}</td>
                                <td>{{ Str::limit($category->description, 60) ?? '-' }}</td>
                                <td>
                                    <span class="badge bg-info text-dark">{{ $category->products_count }}</span>
                                </td>
                                <td>
                                    @if ($category->is_active)
                                        <span class="badge bg-success">Habilitada</span>
                                    @else
                                        <span class="badge bg-danger">Inhabilitada</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-2">
                                        <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-sm btn-outline-primary" title="Editar categoría">
                                            Editar
                                        </a>
                                        <form method="POST" action="{{ route('admin.categories.toggle-status', $category) }}" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            @if ($category->is_active)
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Inhabilitar categoría">
                                                    Inhabilitar
                                                </button>
                                            @else
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Habilitar categoría">
                                                    Habilitar
                                                </button>
                                            @endif
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    {{ __('No se encontraron categorías registradas.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($categories->hasPages())
            <div class="card-footer bg-white">
                {{ $categories->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
