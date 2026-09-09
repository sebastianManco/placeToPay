<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RegisterUserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Public routes
Route::get('/', [DashboardController::class, 'index']);
Route::get('/home', [DashboardController::class, 'index'])->name('home');
Route::get('/showcase', [DashboardController::class, 'index'])->name('showcase.index');

// Shopping cart and order review routes
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/items', [CartController::class, 'addItem'])->name('cart.items.store');
Route::put('/cart/items/{itemId}', [CartController::class, 'updateItem'])->name('cart.items.update');
Route::delete('/cart/items/{itemId}', [CartController::class, 'removeItem'])->name('cart.items.destroy');
Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');
Route::get('/cart/checkout', [CartController::class, 'checkout'])->name('cart.checkout');
Route::post('/cart/checkout/confirm', [CartController::class, 'confirm'])->name('cart.checkout.confirm');

// PlaceToPay Payment Gateway Routes
Route::post('/payment/notification', [PaymentController::class, 'webhook'])->name('payment.notification');
Route::get('/payment/{order}/response', [PaymentController::class, 'processResponse'])->name('payment.response');

// Guest routes (Authentication & Registration)
Route::middleware('guest')->group(function () {
    Route::get('/home/login', [LoginController::class, 'index'])->name('login');
    Route::post('/home/login', [LoginController::class, 'login'])->name('login.perform');
    Route::post('/login', [LoginController::class, 'login']);

    Route::get('/home/register', [RegisterUserController::class, 'create'])->name('register');
    Route::post('/home/registered', [RegisterUserController::class, 'store'])->name('register.store');
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Email verification routes
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // Verified users routes
    Route::middleware('verified')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Customer orders routes
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/my-orders', fn() => redirect()->route('orders.index'))->name('orders.my-orders');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::match(['get', 'post'], '/payment/{order}/pay', [PaymentController::class, 'pay'])->name('payment.pay');
        Route::match(['get', 'post'], '/orders/{order}/retry-payment', [PaymentController::class, 'pay'])->name('orders.retry-payment');

        // Admin routes
        Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
            // Client management routes
            Route::middleware('permission:clients.view')->group(function () {
                Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
            });
            Route::middleware('permission:clients.edit')->group(function () {
                Route::get('/clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
                Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
            });
            Route::middleware('permission:clients.toggle-status')->group(function () {
                Route::patch('/clients/{user}/toggle-status', [ClientController::class, 'toggleStatus'])->name('clients.toggle-status');
            });

            // Product management routes
            Route::middleware('permission:products.view')->group(function () {
                Route::get('/products', [ProductController::class, 'index'])->name('products.index');
            });
            Route::middleware('permission:products.export')->group(function () {
                Route::get('/products/export', [ProductController::class, 'export'])->name('products.export');
            });
            Route::middleware('permission:products.import')->group(function () {
                Route::post('/products/import', [ProductController::class, 'import'])->name('products.import');
            });
            Route::middleware('permission:products.create')->group(function () {
                Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
                Route::post('/products', [ProductController::class, 'store'])->name('products.store');
            });
            Route::middleware('permission:products.edit')->group(function () {
                Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
                Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
            });
            Route::middleware('permission:products.toggle-status')->group(function () {
                Route::patch('/products/{product}/toggle-status', [ProductController::class, 'toggleStatus'])->name('products.toggle-status');
            });

            // Category management routes
            Route::middleware('permission:categories.view')->group(function () {
                Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
            });
            Route::middleware('permission:categories.create')->group(function () {
                Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
                Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
            });
            Route::middleware('permission:categories.edit')->group(function () {
                Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
                Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            });
            Route::middleware('permission:categories.toggle-status')->group(function () {
                Route::patch('/categories/{category}/toggle-status', [CategoryController::class, 'toggleStatus'])->name('categories.toggle-status');
            });

            // Order management routes
            Route::middleware('permission:orders.view')->group(function () {
                Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
                Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
            });
            Route::middleware('permission:orders.update-status')->group(function () {
                Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.update-status');
            });

            // Business intelligence & report generation routes
            Route::middleware('permission:reports.view')->group(function () {
                Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
                Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
                Route::get('/reports/{report}/status', [ReportController::class, 'status'])->name('reports.status');
            });
            Route::middleware('permission:reports.create')->group(function () {
                Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');
            });
            Route::middleware('permission:reports.download')->group(function () {
                Route::get('/reports/{report}/download/{format?}', [ReportController::class, 'download'])->name('reports.download');
            });

            // Roles & Permissions (ACL) management routes
            Route::middleware('permission:roles.view')->group(function () {
                Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
            });
            Route::middleware('permission:roles.create')->group(function () {
                Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
                Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
            });
            Route::middleware('permission:roles.edit')->group(function () {
                Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
                Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
            });
            Route::middleware('permission:roles.delete')->group(function () {
                Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
            });
        });
    });
});