<?php

use App\Http\Controllers\Api\V1\CategoryApiController;
use App\Http\Controllers\Api\V1\ProductApiController;
use App\Http\Controllers\Api\V1\ReportApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Version 1
|--------------------------------------------------------------------------
|
| Rutas RESTful estructuradas de acuerdo a las directrices de Postman:
| - Sustantivos en plural para las colecciones (/products, /categories, /reports)
| - Jerarquía lógica para relaciones (/categories/{category}/products)
| - Verbos HTTP con semántica estricta (GET, POST, PUT, PATCH, DELETE)
| - Versionamiento explícito en la URI (/api/v1/...)
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    // =========================================================================
    // CATEGORÍAS (/api/v1/categories)
    // =========================================================================
    Route::get('categories', [CategoryApiController::class, 'index'])->name('categories.index');
    Route::post('categories', [CategoryApiController::class, 'store'])->name('categories.store');
    Route::get('categories/{category}', [CategoryApiController::class, 'show'])->name('categories.show');
    Route::put('categories/{category}', [CategoryApiController::class, 'update'])->name('categories.update');
    Route::patch('categories/{category}', [CategoryApiController::class, 'update'])->name('categories.patch');
    Route::delete('categories/{category}', [CategoryApiController::class, 'destroy'])->name('categories.destroy');
    Route::get('categories/{category}/products', [CategoryApiController::class, 'products'])->name('categories.products');

    // =========================================================================
    // PRODUCTOS (/api/v1/products)
    // =========================================================================
    Route::get('products', [ProductApiController::class, 'index'])->name('products.index');
    Route::post('products', [ProductApiController::class, 'store'])->name('products.store');
    Route::get('products/export', [ProductApiController::class, 'export'])->name('products.export');
    Route::post('products/import', [ProductApiController::class, 'import'])->name('products.import');
    Route::get('products/{product}', [ProductApiController::class, 'show'])->name('products.show');
    Route::put('products/{product}', [ProductApiController::class, 'update'])->name('products.update');
    Route::patch('products/{product}', [ProductApiController::class, 'update'])->name('products.patch');
    Route::patch('products/{product}/stock', [ProductApiController::class, 'updateStock'])->name('products.stock');
    Route::delete('products/{product}', [ProductApiController::class, 'destroy'])->name('products.destroy');

    // =========================================================================
    // REPORTES Y ANALÍTICAS (/api/v1/reports)
    // =========================================================================
    Route::prefix('reports')->name('reports.')->group(function () {
        // Métricas analíticas en tiempo real
        Route::get('metrics/sales', [ReportApiController::class, 'salesMetrics'])->name('metrics.sales');
        Route::get('metrics/payments', [ReportApiController::class, 'paymentMetrics'])->name('metrics.payments');
        Route::get('metrics/top-products', [ReportApiController::class, 'topProductsMetrics'])->name('metrics.top-products');
        Route::get('metrics/inventory-alerts', [ReportApiController::class, 'inventoryAlertsMetrics'])->name('metrics.inventory-alerts');

        // CRUD y generación asíncrona de reportes
        Route::get('/', [ReportApiController::class, 'index'])->name('index');
        Route::post('/', [ReportApiController::class, 'store'])->name('store');
        Route::get('{report}', [ReportApiController::class, 'show'])->name('show');
        Route::get('{report}/download', [ReportApiController::class, 'download'])->name('download');
        Route::delete('{report}', [ReportApiController::class, 'destroy'])->name('destroy');
    });
});
