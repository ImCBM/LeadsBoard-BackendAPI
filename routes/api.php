<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\DashboardStatsController;
use App\Http\Controllers\Api\WebhookController;

/*
|--------------------------------------------------------------------------
| API Routes — /api/v1/...
|--------------------------------------------------------------------------
|
| All API routes are prefixed with /api/v1.
| 
| Authentication modes:
|   - Webhook routes: ValidateWebhookToken middleware (static Bearer token)
|   - API routes: ValidateApiKey middleware (per-key rate limits)
|   - Auth routes: Sanctum token (login/logout/me)
|
*/

Route::prefix('v1')->group(function () {

    // ─── Webhook Routes (n8n and external integrations) ────────────
    // Protected by static webhook token (WEBHOOK_SECRET in .env)
    Route::prefix('webhook')
        ->middleware(\App\Http\Middleware\ValidateWebhookToken::class)
        ->group(function () {
            Route::post('/leads', [WebhookController::class, 'store']);
            Route::post('/leads/bulk', [WebhookController::class, 'bulkStore']);
        });

    // ─── Auth Routes (public) ──────────────────────────────────────
    Route::post('/auth/login', [AuthController::class, 'login']);

    // ─── Authenticated API Routes ──────────────────────────────────
    // Protected by Sanctum token OR API key
    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Leads CRUD
        Route::get('/leads/export/csv', [LeadController::class, 'exportCsv']);
        Route::get('/leads/filters', [LeadController::class, 'filters']);
        Route::apiResource('leads', LeadController::class);

        // Dashboard Stats
        Route::prefix('stats')->group(function () {
            Route::get('/summary', [DashboardStatsController::class, 'summary']);
            Route::get('/by-industry', [DashboardStatsController::class, 'byIndustry']);
            Route::get('/by-title-tier', [DashboardStatsController::class, 'byTitleTier']);
            Route::get('/by-status', [DashboardStatsController::class, 'byStatus']);
            Route::get('/by-country', [DashboardStatsController::class, 'byCountry']);
            Route::get('/timeline', [DashboardStatsController::class, 'timeline']);
        });
    });

    // ─── API Key Protected Routes ──────────────────────────────────
    // Alternative auth for external consumers with API keys
    Route::middleware(\App\Http\Middleware\ValidateApiKey::class)
        ->prefix('external')
        ->group(function () {
            Route::get('/leads', [LeadController::class, 'index']);
            Route::get('/leads/export/csv', [LeadController::class, 'exportCsv']);
            Route::get('/leads/filters', [LeadController::class, 'filters']);
            Route::get('/leads/{lead}', [LeadController::class, 'show']);
            Route::get('/stats/summary', [DashboardStatsController::class, 'summary']);
        });
});