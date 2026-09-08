@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>{{ __('Editar Categoría') }}</h2>
            <p class="text-muted mb-0">{{ __('Modifique los datos de la categoría ') }} <strong>{{ $category->name }}</strong>
                <span class="badge bg-info text-dark ms-2">{{ $category->products_count }} {{ __('productos') }}</span>
            </p>
        </div>
        <a href="{{ route('admin.categories.index') }}" class="btn btn-outline-secondary">
            &larr; {{ __('Volver al listado') }}
        </a>
    </div>

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('admin.categories.update', $category) }}">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <!-- ID (Solo Lectura) -->
                    <div class="col-md-2">
                        <label for="id" class="form-label">
                            {{ __('ID') }}
                            <small class="text-muted">({{ __('No modificable') }})</small>
                        </label>
                        <input type="text"
                               id="id"
                               class="form-control bg-light"
                               value="{{ $category->id }}"
                               readonly
                               disabled>
                    </div>

                    <!-- Nombre -->
                    <div class="col-md-5">
                        <label for="name" class="form-label">{{ __('Nombre de la Categoría') }} <span class="text-danger">*</span></label>
                        <input type="text"
                               id="name"
                               name="name"
                               class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $category->name) }}"
                               required
                               maxlength="100">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Estado -->
                    <div class="col-md-5">
                        <label for="is_active" class="form-label">{{ __('Estado de la Categoría') }}</label>
                        <select id="is_active" name="is_active" class="form-select @error('is_active') is-invalid @enderror">
                            <option value="1" {{ old('is_active', $category->is_active ? '1' : '0') === '1' ? 'selected' : '' }}>
                                {{ __('Habilitada') }}
                            </option>
                            <option value="0" {{ old('is_active', $category->is_active ? '1' : '0') === '0' ? 'selected' : '' }}>
                                {{ __('Inhabilitada') }}
                            </option>
                        </select>
                        @error('is_active')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Descripción -->
                    <div class="col-md-12">
                        <label for="description" class="form-label">{{ __('Descripción') }}</label>
                        <textarea id="description"
                                  name="description"
                                  class="form-control @error('description') is-invalid @enderror"
                                  rows="3"
                                  maxlength="1000">{{ old('description', $category->description) }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.categories.index') }}" class="btn btn-outline-secondary">
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
