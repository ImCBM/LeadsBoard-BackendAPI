<?php

/**
 * Standalone Database Setup & Migration Runner for Hostinger
 * Direct execution without routing dependency.
 */

// 1. Secret Key Verification
$secret = $_GET['secret'] ?? '';
$expectedSecret = 'b2bleadscraper-prod-webhook-token-7f9a2e8c4d1b';

if (empty($secret) || !hash_equals($expectedSecret, (string) $secret)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized: Invalid secret token.']);
    exit;
}

// 2. Bootstrap Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);

// 3. Ensure SQLite database file exists
$dbPath = __DIR__ . '/../database/database.sqlite';
if (!file_exists($dbPath)) {
    @touch($dbPath);
    @chmod($dbPath, 0666);
}

header('Content-Type: application/json');

try {
    // 4. Run Migrations
    $kernel->call('migrate', ['--force' => true]);
    $migrateOutput = \Illuminate\Support\Facades\Artisan::output();

    // 5. Run Seeds (Admin User + Leads)
    $kernel->call('db:seed', ['--force' => true]);
    $seedOutput = \Illuminate\Support\Facades\Artisan::output();

    // 6. Clear & Cache config
    $kernel->call('config:clear');
    $kernel->call('cache:clear');

    echo json_encode([
        'status' => 'success',
        'message' => 'Database migrations and seeds executed successfully!',
        'database_file' => realpath($dbPath),
        'migrate_output' => $migrateOutput,
        'seed_output' => $seedOutput,
    ], JSON_PRETTY_PRINT);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ], JSON_PRETTY_PRINT);
}
