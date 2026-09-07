<?php

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

Route::get('/', [HomeController::class, 'index']);
Route::get('/home', [HomeController::class, 'index'])->name('home');
Route::get('/home/register', [RegisterUserController::class, 'create'])->name('register');
Route::post('/home/registered', [RegisterUserController::class, 'store'])->name('register.store');
Route::get('/home/login', [LoginController::class, 'index'])->name('login');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');