<?php

use App\Http\Controllers\Api\V1\AuthApiController;
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
| - Seguridad y autorización mediante Laravel Sanctum y control de acceso ACL
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    // =========================================================================
    // AUTENTICACIÓN Y SESIÓN (/api/v1/auth)
    // =========================================================================
    Route::prefix('auth')->name('auth.')->group(function () {
        // Endpoint público: inicio de sesión y emisión de Bearer Token
        Route::post('login', [AuthApiController::class, 'login'])->name('login');

        // Endpoints protegidos por token Bearer (Sanctum)
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthApiController::class, 'logout'])->name('logout');
            Route::get('me', [AuthApiController::class, 'me'])->name('me');
        });
    });

    // =========================================================================
    // CATEGORÍAS (/api/v1/categories)
    // =========================================================================
    // Endpoints públicos de solo lectura
    Route::get('categories', [CategoryApiController::class, 'index'])->name('categories.index');
    Route::get('categories/{category}', [CategoryApiController::class, 'show'])->name('categories.show');
    Route::get('categories/{category}/products', [CategoryApiController::class, 'products'])->name('categories.products');

    // Endpoints administrativos protegidos con Sanctum y ACL
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('categories', [CategoryApiController::class, 'store'])
            ->middleware('permission:categories.create')
            ->name('categories.store');

        Route::put('categories/{category}', [CategoryApiController::class, 'update'])
            ->middleware('permission:categories.edit')
            ->name('categories.update');

        Route::patch('categories/{category}', [CategoryApiController::class, 'update'])
            ->middleware('permission:categories.edit')
            ->name('categories.patch');

        Route::delete('categories/{category}', [CategoryApiController::class, 'destroy'])
            ->middleware('permission:categories.edit,categories.toggle-status')
            ->name('categories.destroy');
    });

    // =========================================================================
    // PRODUCTOS (/api/v1/products)
    // =========================================================================
    // Catálogo comercial público (listado)
    Route::get('products', [ProductApiController::class, 'index'])->name('products.index');

    // Endpoints administrativos protegidos con Sanctum y ACL
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('products/export', [ProductApiController::class, 'export'])
            ->middleware('permission:products.export')
            ->name('products.export');

        Route::post('products/import', [ProductApiController::class, 'import'])
            ->middleware('permission:products.import')
            ->name('products.import');

        Route::post('products', [ProductApiController::class, 'store'])
            ->middleware('permission:products.create')
            ->name('products.store');

        Route::put('products/{product}', [ProductApiController::class, 'update'])
            ->middleware('permission:products.edit')
            ->name('products.update');

        Route::patch('products/{product}', [ProductApiController::class, 'update'])
            ->middleware('permission:products.edit')
            ->name('products.patch');

        Route::patch('products/{product}/stock', [ProductApiController::class, 'updateStock'])
            ->middleware('permission:products.edit')
            ->name('products.stock');

        Route::delete('products/{product}', [ProductApiController::class, 'destroy'])
            ->middleware('permission:products.edit,products.toggle-status')
            ->name('products.destroy');
    });

    // Detalle público de producto (definido después de export para evitar colisiones)
    Route::get('products/{product}', [ProductApiController::class, 'show'])->name('products.show');

    // =========================================================================
    // REPORTES Y ANALÍTICAS (/api/v1/reports)
    // Todo el módulo administrativo y de inteligencia está protegido con Sanctum
    // =========================================================================
    Route::prefix('reports')->name('reports.')->middleware('auth:sanctum')->group(function () {
        // Métricas analíticas en tiempo real
        Route::get('metrics/sales', [ReportApiController::class, 'salesMetrics'])
            ->middleware('permission:reports.view')
            ->name('metrics.sales');

        Route::get('metrics/payments', [ReportApiController::class, 'paymentMetrics'])
            ->middleware('permission:reports.view')
            ->name('metrics.payments');

        Route::get('metrics/top-products', [ReportApiController::class, 'topProductsMetrics'])
            ->middleware('permission:reports.view')
            ->name('metrics.top-products');

        Route::get('metrics/inventory-alerts', [ReportApiController::class, 'inventoryAlertsMetrics'])
            ->middleware('permission:reports.view')
            ->name('metrics.inventory-alerts');

        // CRUD y generación asíncrona de reportes
        Route::get('/', [ReportApiController::class, 'index'])
            ->middleware('permission:reports.view')
            ->name('index');

        Route::post('/', [ReportApiController::class, 'store'])
            ->middleware('permission:reports.create')
            ->name('store');

        Route::get('{report}', [ReportApiController::class, 'show'])
            ->middleware('permission:reports.view')
            ->name('show');

        Route::get('{report}/download', [ReportApiController::class, 'download'])
            ->middleware('permission:reports.download')
            ->name('download');

        Route::delete('{report}', [ReportApiController::class, 'destroy'])
            ->middleware('permission:reports.view')
            ->name('destroy');
    });
});
