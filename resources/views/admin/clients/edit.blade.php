@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>{{ __('Editar Cliente') }}</h2>
            <p class="text-muted mb-0">{{ __('Modifique los datos del cliente ') }} <strong>{{ $client->name }} {{ $client->last_name }}</strong></p>
        </div>
        <a href="{{ route('admin.clients.index') }}" class="btn btn-outline-secondary">
            &larr; {{ __('Volver al listado') }}
        </a>
    </div>

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('admin.clients.update', $client) }}">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <!-- Identificación (Solo Lectura) -->
                    <div class="col-md-6">
                        <label for="identification" class="form-label">
                            {{ __('Identificación / Cédula') }}
                            <small class="text-muted">({{ __('No modificable') }})</small>
                        </label>
                        <input type="text"
                               id="identification"
                               class="form-control bg-light"
                               value="{{ $client->identification }}"
                               readonly
                               disabled>
                    </div>

                    <!-- Nombre de Usuario (Solo Lectura) -->
                    <div class="col-md-6">
                        <label for="user_name" class="form-label">
                            {{ __('Nombre de Usuario') }}
                            <small class="text-muted">({{ __('No modificable') }})</small>
                        </label>
                        <input type="text"
                               id="user_name"
                               class="form-control bg-light"
                               value="{{ $client->user_name }}"
                               readonly
                               disabled>
                    </div>

                    <!-- Nombres -->
                    <div class="col-md-6">
                        <label for="name" class="form-label">{{ __('Nombres') }} <span class="text-danger">*</span></label>
                        <input type="text"
                               id="name"
                               name="name"
                               class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $client->name) }}"
                               required
                               maxlength="50">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Apellidos -->
                    <div class="col-md-6">
                        <label for="last_name" class="form-label">{{ __('Apellidos') }} <span class="text-danger">*</span></label>
                        <input type="text"
                               id="last_name"
                               name="last_name"
                               class="form-control @error('last_name') is-invalid @enderror"
                               value="{{ old('last_name', $client->last_name) }}"
                               required
                               maxlength="50">
                        @error('last_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Email -->
                    <div class="col-md-6">
                        <label for="email" class="form-label">{{ __('Correo Electrónico') }} <span class="text-danger">*</span></label>
                        <input type="email"
                               id="email"
                               name="email"
                               class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email', $client->email) }}"
                               required
                               maxlength="100">
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Teléfono -->
                    <div class="col-md-6">
                        <label for="phone" class="form-label">{{ __('Teléfono') }} <span class="text-danger">*</span></label>
                        <input type="text"
                               id="phone"
                               name="phone"
                               class="form-control @error('phone') is-invalid @enderror"
                               value="{{ old('phone', $client->phone) }}"
                               required
                               maxlength="20">
                        @error('phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Dirección -->
                    <div class="col-md-8">
                        <label for="direction" class="form-label">{{ __('Dirección de Residencia') }} <span class="text-danger">*</span></label>
                        <input type="text"
                               id="direction"
                               name="direction"
                               class="form-control @error('direction') is-invalid @enderror"
                               value="{{ old('direction', $client->direction) }}"
                               required
                               maxlength="100">
                        @error('direction')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Estado Activo/Inactivo -->
                    <div class="col-md-4">
                        <label for="is_active" class="form-label">{{ __('Estado de la Cuenta') }}</label>
                        <select id="is_active" name="is_active" class="form-select @error('is_active') is-invalid @enderror">
                            <option value="1" {{ old('is_active', $client->is_active ? '1' : '0') === '1' ? 'selected' : '' }}>
                                {{ __('Activo') }}
                            </option>
                            <option value="0" {{ old('is_active', $client->is_active ? '1' : '0') === '0' ? 'selected' : '' }}>
                                {{ __('Inactivo') }}
                            </option>
                        </select>
                        @error('is_active')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    @if(isset($roles) && $roles->isNotEmpty())
                        <!-- Roles y Permisos (ACL) -->
                        <div class="col-12 mt-3">
                            <label class="form-label fw-bold">{{ __('Roles Asignados (Control de Acceso / ACL)') }}</label>
                            <div class="card bg-light border-0 p-3">
                                <div class="row">
                                    @foreach($roles as $r)
                                        <div class="col-md-4 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input"
                                                       type="checkbox"
                                                       name="roles[]"
                                                       value="{{ $r->id }}"
                                                       id="role_{{ $r->id }}"
                                                       {{ in_array($r->id, old('roles', $clientRoles ?? [])) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="role_{{ $r->id }}">
                                                    <span class="fw-bold">{{ $r->name }}</span>
                                                    @if($r->description)
                                                        <br><small class="text-muted">{{ $r->description }}</small>
                                                    @endif
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.clients.index') }}" class="btn btn-outline-secondary">
                        {{ __('Cancelar') }}
                    </a>
                    <button type="submit" class="btn btn-primary">
                        {{ __('Guardar Cambios') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
