@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>{{ __('Administración de Clientes') }}</h2>
        <span class="badge bg-primary fs-6">{{ __('Total Clientes: ') }} {{ $clients->total() }}</span>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
        </div>
    @endif

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.clients.index') }}" class="row g-3">
                <div class="col-md-5">
                    <input type="text"
                           name="search"
                           class="form-control"
                           placeholder="Buscar por cédula, nombre o email..."
                           value="{{ request('search') }}">
                </div>
                <div class="col-md-4">
                    <select name="status" class="form-select">
                        <option value="">-- Todos los estados --</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Activos</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactivos</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                    @if(request('search') || request('status'))
                        <a href="{{ route('admin.clients.index') }}" class="btn btn-outline-secondary">Limpiar</a>
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
                            <th>{{ __('Identificación') }}</th>
                            <th>{{ __('Nombre Completo') }}</th>
                            <th>{{ __('Email') }}</th>
                            <th>{{ __('Teléfono') }}</th>
                            <th>{{ __('Email Verificado') }}</th>
                            <th>{{ __('Estado') }}</th>
                            <th class="text-center">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($clients as $client)
                            <tr>
                                <td><strong>{{ $client->identification }}</strong></td>
                                <td>{{ $client->name }} {{ $client->last_Name }}</td>
                                <td>{{ $client->email }}</td>
                                <td>{{ $client->phone ?? '-' }}</td>
                                <td>
                                    @if ($client->hasVerifiedEmail())
                                        <span class="badge bg-success">Verificado</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Pendiente</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($client->is_active)
                                        <span class="badge bg-success">Activo</span>
                                    @else
                                        <span class="badge bg-danger">Inactivo</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <form method="POST" action="{{ route('admin.clients.toggle-status', $client) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        @if ($client->is_active)
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Desactivar cliente">
                                                Desactivar
                                            </button>
                                        @else
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Activar cliente">
                                                Activar
                                            </button>
                                        @endif
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    {{ __('No se encontraron clientes registrados.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($clients->hasPages())
            <div class="card-footer bg-white">
                {{ $clients->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
