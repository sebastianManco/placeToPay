<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController;
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
            Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
            Route::get('/clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
            Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
            Route::patch('/clients/{user}/toggle-status', [ClientController::class, 'toggleStatus'])->name('clients.toggle-status');

            // Product management routes
            Route::get('/products', [ProductController::class, 'index'])->name('products.index');
            Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
            Route::post('/products', [ProductController::class, 'store'])->name('products.store');
            Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
            Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
            Route::patch('/products/{product}/toggle-status', [ProductController::class, 'toggleStatus'])->name('products.toggle-status');

            // Category management routes
            Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
            Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
            Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::patch('/categories/{category}/toggle-status', [CategoryController::class, 'toggleStatus'])->name('categories.toggle-status');

            // Order management routes
            Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
            Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.update-status');
        });
    });
});