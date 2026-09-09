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

// ── Secure Hostinger Database Migration Runner ─────────────
// Allows running migrations & seeders via browser (protected by WEBHOOK_SECRET)
Route::get('/system/setup-database', function (\Illuminate\Http\Request $request) {
    $secret = $request->query('secret');
    $expectedSecret = config('services.webhook.secret');

    if (empty($secret) || empty($expectedSecret) || !hash_equals($expectedSecret, (string) $secret)) {
        return response()->json(['error' => 'Unauthorized: Invalid or missing secret token.'], 403);
    }

    try {
        if (config('database.default') === 'sqlite') {
            $dbPath = config('database.connections.sqlite.database') ?: database_path('database.sqlite');
            if (!file_exists($dbPath)) {
                @touch($dbPath);
                @chmod($dbPath, 0666);
            }
        }

        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $migrateOutput = \Illuminate\Support\Facades\Artisan::output();

        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
        $seedOutput = \Illuminate\Support\Facades\Artisan::output();

        \Illuminate\Support\Facades\Artisan::call('config:clear');
        \Illuminate\Support\Facades\Artisan::call('cache:clear');

        return response()->json([
            'status' => 'success',
            'message' => 'Database migrations and seeds executed successfully!',
            'migrate_output' => $migrateOutput,
            'seed_output' => $seedOutput,
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
});

