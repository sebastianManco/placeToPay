@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>{{ __('Crear Categoría') }}</h2>
            <p class="text-muted mb-0">{{ __('Complete los datos de la nueva categoría') }}</p>
        </div>
        <a href="{{ route('admin.categories.index') }}" class="btn btn-outline-secondary">
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
            <form method="POST" action="{{ route('admin.categories.store') }}">
                @csrf

                <div class="row g-3">
                    <!-- Nombre -->
                    <div class="col-md-6">
                        <label for="name" class="form-label">{{ __('Nombre de la Categoría') }} <span class="text-danger">*</span></label>
                        <input type="text"
                               id="name"
                               name="name"
                               class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name') }}"
                               required
                               maxlength="100">
                        @error('name')
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
                                  maxlength="1000">{{ old('description') }}</textarea>
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
                    <button type="submit" class="btn btn-success">
                        {{ __('Crear Categoría') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
