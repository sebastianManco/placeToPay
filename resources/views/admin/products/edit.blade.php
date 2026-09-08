@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>{{ __('Editar Producto') }}</h2>
            <p class="text-muted mb-0">{{ __('Modifique los datos del producto ') }} <strong>{{ $product->name }}</strong></p>
        </div>
        <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">
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
            <form method="POST" action="{{ route('admin.products.update', $product) }}" enctype="multipart/form-data">
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
                               value="{{ $product->id }}"
                               readonly
                               disabled>
                    </div>

                    <!-- Nombre -->
                    <div class="col-md-5">
                        <label for="name" class="form-label">{{ __('Nombre del Producto') }} <span class="text-danger">*</span></label>
                        <input type="text"
                               id="name"
                               name="name"
                               class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $product->name) }}"
                               required
                               maxlength="100">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Estado -->
                    <div class="col-md-5">
                        <label for="is_active" class="form-label">{{ __('Estado del Producto') }}</label>
                        <select id="is_active" name="is_active" class="form-select @error('is_active') is-invalid @enderror">
                            <option value="1" {{ old('is_active', $product->is_active ? '1' : '0') === '1' ? 'selected' : '' }}>
                                {{ __('Habilitado') }}
                            </option>
                            <option value="0" {{ old('is_active', $product->is_active ? '1' : '0') === '0' ? 'selected' : '' }}>
                                {{ __('Inhabilitado') }}
                            </option>
                        </select>
                        @error('is_active')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Categoría -->
                    <div class="col-md-4">
                        <label for="category_id" class="form-label">{{ __('Categoría') }}</label>
                        <select id="category_id"
                                name="category_id"
                                class="form-select @error('category_id') is-invalid @enderror">
                            <option value="">-- Sin categoría --</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('category_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Precio -->
                    <div class="col-md-4">
                        <label for="price" class="form-label">{{ __('Precio') }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number"
                                   id="price"
                                   name="price"
                                   class="form-control @error('price') is-invalid @enderror"
                                   value="{{ old('price', $product->price) }}"
                                   required
                                   min="0"
                                   step="0.01">
                            @error('price')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Stock -->
                    <div class="col-md-4">
                        <label for="stock" class="form-label">{{ __('Stock') }} <span class="text-danger">*</span></label>
                        <input type="number"
                               id="stock"
                               name="stock"
                               class="form-control @error('stock') is-invalid @enderror"
                               value="{{ old('stock', $product->stock) }}"
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
                                  maxlength="1000">{{ old('description', $product->description) }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Imagen Actual -->
                    @if ($product->image)
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Imagen Actual') }}</label>
                            <div>
                                <img src="{{ asset('storage/' . $product->image) }}"
                                     alt="{{ $product->name }}"
                                     class="img-thumbnail"
                                     style="max-height: 150px;">
                            </div>
                        </div>
                    @endif

                    <!-- Nueva Imagen -->
                    <div class="col-md-6">
                        <label for="image" class="form-label">{{ $product->image ? __('Cambiar Imagen') : __('Imagen del Producto') }}</label>
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
                    <button type="submit" class="btn btn-primary">
                        {{ __('Guardar Cambios') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
