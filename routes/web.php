<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Minimal Blade dashboard with session-based auth.
|
*/

// Redirect root to dashboard
Route::get('/', function () {
    return redirect('/dashboard');
});

// Auth routes (guest only)
Route::middleware('guest')->group(function () {
    Route::get('/login', [DashboardController::class, 'showLogin'])->name('login');
    Route::post('/login', [DashboardController::class, 'login']);
});

// Protected dashboard routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [DashboardController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/export/csv', [DashboardController::class, 'exportCsv'])->name('dashboard.export');
    
    // API Key Management
    Route::resource('dashboard/api-keys', \App\Http\Controllers\ApiKeyController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->names('dashboard.api-keys');
});
