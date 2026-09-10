@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>{{ __('Crear Producto') }}</h2>
            <p class="text-muted mb-0">{{ __('Complete los datos del nuevo producto') }}</p>
        </div>
        <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">
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
            <form method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="row g-3">
                    <!-- Nombre -->
                    <div class="col-md-6">
                        <label for="name" class="form-label">{{ __('Nombre del Producto') }} <span class="text-danger">*</span></label>
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

                    <!-- Categoría -->
                    <div class="col-md-6">
                        <label for="category_id" class="form-label">{{ __('Categoría') }}</label>
                        <select id="category_id"
                                name="category_id"
                                class="form-select @error('category_id') is-invalid @enderror">
                            <option value="">-- Sin categoría --</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('category_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Precio -->
                    <div class="col-md-3">
                        <label for="price" class="form-label">{{ __('Precio') }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number"
                                   id="price"
                                   name="price"
                                   class="form-control @error('price') is-invalid @enderror"
                                   value="{{ old('price') }}"
                                   required
                                   min="0"
                                   step="0.01">
                            @error('price')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Stock -->
                    <div class="col-md-3">
                        <label for="stock" class="form-label">{{ __('Stock') }} <span class="text-danger">*</span></label>
                        <input type="number"
                               id="stock"
                               name="stock"
                               class="form-control @error('stock') is-invalid @enderror"
                               value="{{ old('stock', 0) }}"
                               required
                               min="0">
                        @error('stock')
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

                    <!-- Imagen -->
                    <div class="col-md-6">
                        <label for="image" class="form-label">{{ __('Imagen del Producto') }}</label>
                        <input type="file"
                               id="image"
                               name="image"
                               class="form-control @error('image') is-invalid @enderror"
                               accept="image/*">
                        <small class="text-muted">{{ __('Formatos: JPG, PNG, GIF. Máximo 2MB.') }}</small>
                        @error('image')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">
                        {{ __('Cancelar') }}
                    </a>
                    <button type="submit" class="btn btn-success">
                        {{ __('Crear Producto') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
