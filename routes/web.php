<?php

use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LoginController;
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
Route::get('/', [HomeController::class, 'index']);
Route::get('/home', [HomeController::class, 'index'])->name('home');

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

        // Admin routes
        Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
            Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
            Route::get('/clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
            Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
            Route::patch('/clients/{user}/toggle-status', [ClientController::class, 'toggleStatus'])->name('clients.toggle-status');
        });
    });
});