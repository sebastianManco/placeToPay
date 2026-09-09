@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>{{ __('Crear Nuevo Rol') }}</h2>
            <p class="text-muted mb-0">{{ __('Define el nombre, identificador y los permisos granulares asignados al rol.') }}</p>
        </div>
        <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">
            &larr; {{ __('Volver al listado') }}
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <h6 class="alert-heading font-weight-bold mb-1">{{ __('Por favor corrige los errores:') }}</h6>
            <ul class="mb-0 pl-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.roles.store') }}">
        @csrf

        {{-- Datos básicos del rol --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white font-weight-bold">
                {{ __('Información del Rol') }}
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label font-weight-bold">{{ __('Nombre del Rol') }} <span class="text-danger">*</span></label>
                        <input type="text"
                               id="name"
                               name="name"
                               class="form-control @error('name') is-invalid @enderror"
                               placeholder="Ej: Gestor de Catálogo"
                               value="{{ old('name') }}"
                               required
                               maxlength="100">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="slug" class="form-label font-weight-bold">{{ __('Identificador (Slug)') }} <span class="text-danger">*</span></label>
                        <input type="text"
                               id="slug"
                               name="slug"
                               class="form-control font-monospace @error('slug') is-invalid @enderror"
                               placeholder="Ej: catalog_manager"
                               value="{{ old('slug') }}"
                               required
                               maxlength="100">
                        <small class="form-text text-muted">{{ __('Solo letras, números, guiones y guiones bajos (sin espacios).') }}</small>
                        @error('slug')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label for="description" class="form-label font-weight-bold">{{ __('Descripción') }}</label>
                        <textarea id="description"
                                  name="description"
                                  rows="2"
                                  class="form-control @error('description') is-invalid @enderror"
                                  placeholder="Describe las responsabilidades y alcance del rol...">{{ old('description') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Matriz de Permisos Granulares agrupados por módulo --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="font-weight-bold">{{ __('Permisos Granulares del Sistema') }}</span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-toggle-all">
                    {{ __('Seleccionar / Deseleccionar Todo') }}
                </button>
            </div>
            <div class="card-body p-4">
                @php
                    $moduleLabels = [
                        'clients' => 'Gestión de Clientes',
                        'products' => 'Gestión de Productos',
                        'categories' => 'Gestión de Categorías',
                        'orders' => 'Gestión de Pedidos',
                        'reports' => 'Inteligencia de Negocio y Reportes',
                        'roles' => 'Control de Acceso y Roles (ACL)',
                    ];
                @endphp

                <div class="row">
                    @foreach ($permissionsByModule as $moduleKey => $permissions)
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border bg-light">
                                <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                                    <strong class="text-primary">{{ $moduleLabels[$moduleKey] ?? ucfirst($moduleKey) }}</strong>
                                    <button type="button"
                                            class="btn btn-sm btn-link p-0 text-muted btn-select-module"
                                            data-module="{{ $moduleKey }}">
                                        {{ __('Alternar módulo') }}
                                    </button>
                                </div>
                                <div class="card-body p-3 bg-white">
                                    @foreach ($permissions as $perm)
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox"
                                                   class="custom-control-input perm-checkbox perm-module-{{ $moduleKey }}"
                                                   id="perm_{{ $perm->id }}"
                                                   name="permissions[]"
                                                   value="{{ $perm->id }}"
                                                   {{ in_array($perm->id, old('permissions', [])) ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="perm_{{ $perm->id }}">
                                                <span class="font-weight-bold text-dark">{{ $perm->name }}</span>
                                                <br>
                                                <span class="text-muted small font-monospace">{{ $perm->slug }}</span>
                                                @if ($perm->description)
                                                    <span class="text-muted small d-block">{{ $perm->description }}</span>
                                                @endif
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-5">
            <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary mr-2">
                {{ __('Cancelar') }}
            </a>
            <button type="submit" class="btn btn-primary px-4">
                {{ __('Crear Rol') }}
            </button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleAllBtn = document.getElementById('btn-toggle-all');
        if (toggleAllBtn) {
            toggleAllBtn.addEventListener('click', function() {
                const checkboxes = document.querySelectorAll('.perm-checkbox');
                const anyUnchecked = Array.from(checkboxes).some(cb => !cb.checked);
                checkboxes.forEach(cb => cb.checked = anyUnchecked);
            });
        }

        document.querySelectorAll('.btn-select-module').forEach(btn => {
            btn.addEventListener('click', function() {
                const moduleKey = this.getAttribute('data-module');
                const checkboxes = document.querySelectorAll('.perm-module-' + moduleKey);
                const anyUnchecked = Array.from(checkboxes).some(cb => !cb.checked);
                checkboxes.forEach(cb => cb.checked = anyUnchecked);
            });
        });
    });
</script>
@endsection
