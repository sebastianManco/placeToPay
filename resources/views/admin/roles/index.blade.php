@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
        <div>
            <h1 class="h2 font-weight-bold mb-1">{{ __('Control de Acceso (ACL): Roles y Permisos') }}</h1>
            <p class="text-muted mb-0">{{ __('Administración de roles y asignación de permisos granulares del sistema.') }}</p>
        </div>
        <div class="mt-2 mt-md-0">
            <a href="{{ route('admin.roles.create') }}" class="btn btn-primary d-flex align-items-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-shield-plus mr-1" viewBox="0 0 16 16">
                    <path d="M5.338 1.59a61.44 61.44 0 0 0-2.837.856.481.481 0 0 0-.328.39c-.554 4.157.726 7.19 2.253 9.188a10.725 10.725 0 0 0 2.287 2.233c.346.244.652.42.893.533.12.057.218.095.293.118a.55.55 0 0 0 .101.025.615.615 0 0 0 .1-.025c.076-.023.174-.061.294-.118.24-.113.547-.29.893-.533a10.726 10.726 0 0 0 2.287-2.233c1.527-1.997 2.807-5.031 2.253-9.188a.48.48 0 0 0-.328-.39c-.651-.213-1.75-.56-2.837-.855C9.552 1.29 8.531 1.067 8 1.067c-.53 0-1.552.223-2.662.524zM5.072.56C6.157.265 7.31 0 8 0s1.843.265 2.928.56c1.11.3 2.229.655 2.887.87a1.54 1.54 0 0 1 1.044 1.262c.599 4.491-.71 7.776-2.463 9.992a11.775 11.775 0 0 1-2.5 2.45c-.341.242-.682.432-.97.567-.29.135-.634.242-.926.242s-.636-.107-.926-.242a7.359 7.359 0 0 1-.97-.567 11.775 11.775 0 0 1-2.5-2.45C1.465 10.488.156 7.203.755 2.712A1.54 1.54 0 0 1 1.8 1.45c.658-.215 1.777-.57 2.887-.87z"/>
                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                </svg>
                {{ __('Crear Nuevo Rol') }}
            </a>
        </div>
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

    {{-- Buscador --}}
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('admin.roles.index') }}">
                <div class="row align-items-center">
                    <div class="col-md-9 mb-2 mb-md-0">
                        <div class="input-group">
                            <input type="text"
                                   name="search"
                                   class="form-control"
                                   placeholder="{{ __('Buscar por nombre, slug o descripción de rol...') }}"
                                   value="{{ request('search') }}">
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="submit">
                                    {{ __('Buscar') }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 text-md-right">
                        @if (request()->filled('search'))
                            <a href="{{ route('admin.roles.index') }}" class="btn btn-link text-muted">
                                {{ __('Limpiar filtro') }}
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Listado de Roles --}}
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="thead-light">
                    <tr>
                        <th scope="col">{{ __('Rol') }}</th>
                        <th scope="col">{{ __('Identificador (Slug)') }}</th>
                        <th scope="col">{{ __('Descripción') }}</th>
                        <th scope="col" class="text-center">{{ __('Permisos') }}</th>
                        <th scope="col" class="text-center">{{ __('Usuarios') }}</th>
                        <th scope="col" class="text-center">{{ __('Tipo') }}</th>
                        <th scope="col" class="text-right">{{ __('Acciones') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($roles as $role)
                        <tr>
                            <td>
                                <strong class="text-dark">{{ $role->name }}</strong>
                            </td>
                            <td>
                                <span class="badge badge-secondary font-monospace">{{ $role->slug }}</span>
                            </td>
                            <td class="text-muted small">
                                {{ $role->description ?: __('Sin descripción') }}
                            </td>
                            <td class="text-center">
                                <span class="badge badge-info px-2 py-1">
                                    {{ $role->permissions->count() }} {{ __('permisos') }}
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-light border text-dark px-2 py-1">
                                    {{ $role->users_count }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if ($role->isSystem() || in_array($role->slug, ['admin', 'client']))
                                    <span class="badge badge-warning">{{ __('Sistema') }}</span>
                                @else
                                    <span class="badge badge-light border">{{ __('Personalizado') }}</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-outline-primary" title="{{ __('Editar') }}">
                                        {{ __('Editar') }}
                                    </a>
                                    @if (! $role->isSystem() && ! in_array($role->slug, ['admin', 'client']))
                                        <form action="{{ route('admin.roles.destroy', $role) }}"
                                              method="POST"
                                              class="d-inline"
                                              onsubmit="return confirm('¿Confirma que desea eliminar el rol {{ $role->name }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger" title="{{ __('Eliminar') }}">
                                                {{ __('Eliminar') }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                {{ __('No se encontraron roles registrados.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($roles->hasPages())
            <div class="card-footer bg-white d-flex justify-content-center border-0 pt-3">
                {{ $roles->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
