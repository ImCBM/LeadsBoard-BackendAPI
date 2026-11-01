<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\DashboardStatsController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\DeveloperApiKeyController;
use App\Http\Controllers\Api\ApiTraceController;
use App\Http\Middleware\AuthenticateApiRequest;
use App\Http\Middleware\RequireScope;
use App\Http\Middleware\ApiTraceLogger;

/*
|--------------------------------------------------------------------------
| API Routes — /api/v1/...
|--------------------------------------------------------------------------
|
| Unified API architecture with multi-auth support.
|
| Authentication modes:
|   - Sanctum tokens: For frontend/dashboard users
|   - API Keys (lb_pub_/lb_sec_): For developer integrations
|
| All API routes are traced via ApiTraceLogger middleware.
|
*/

Route::prefix('v1')->group(function () {

    // ─── Public Auth Routes (no auth required) ─────────────────
    Route::post('/auth/login', [AuthController::class, 'login']);

    // ─── Authenticated Routes ──────────────────────────────────
    // Protected by unified multi-auth (Sanctum OR API Key)
    // All routes are traced for audit logging
    Route::middleware([AuthenticateApiRequest::class, ApiTraceLogger::class])->group(function () {

        // ── Auth Management (Sanctum only) ─────────────────────
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // ── Leads: Read ────────────────────────────────────────
        Route::middleware('scope:leads:read')->group(function () {
            Route::get('/leads', [LeadController::class, 'index']);
            Route::get('/leads/filters', [LeadController::class, 'filters']);
            Route::get('/leads/{lead}', [LeadController::class, 'show']);
        });

        // ── Leads: Export ──────────────────────────────────────
        Route::middleware('scope:leads:export')->group(function () {
            Route::get('/leads/export/csv', [LeadController::class, 'exportCsv']);
        });

        // ── Leads: Import ──────────────────────────────────────
        Route::middleware('scope:leads:import')->group(function () {
            Route::post('/leads/import/csv', [LeadController::class, 'importCsv']);
        });

        // ── Leads: Write (Create/Update) ───────────────────────
        Route::middleware('scope:leads:write')->group(function () {
            Route::post('/leads', [LeadController::class, 'store']);
            Route::put('/leads/{lead}', [LeadController::class, 'update']);
            Route::patch('/leads/{lead}', [LeadController::class, 'update']);
            Route::post('/leads/bulk', [LeadController::class, 'bulkStore']);
            Route::post('/leads/bulk-tag', [LeadController::class, 'bulkTag']);
        });

        // ── Leads: Delete ──────────────────────────────────────
        Route::middleware('scope:leads:delete')->group(function () {
            Route::delete('/leads/{lead}', [LeadController::class, 'destroy']);
            Route::post('/leads/bulk-delete', [LeadController::class, 'bulkDelete']);
            Route::delete('/leads/bulk', [LeadController::class, 'bulkDelete']);
        });

        // ── Tags: Read ─────────────────────────────────────────
        Route::middleware('scope:tags:read')->group(function () {
            Route::get('/tags', [TagController::class, 'index']);
            Route::get('/tags/{tag}', [TagController::class, 'show']);
        });

        // ── Tags: Write ────────────────────────────────────────
        Route::middleware('scope:tags:write')->group(function () {
            Route::post('/tags', [TagController::class, 'store']);
        });

        // ── Tags: Delete ───────────────────────────────────────
        Route::middleware('scope:tags:delete')->group(function () {
            Route::delete('/tags/{tag}', [TagController::class, 'destroy']);
        });

        // ── Stats: Read ────────────────────────────────────────
        Route::middleware('scope:stats:read')->prefix('stats')->group(function () {
            Route::get('/summary', [DashboardStatsController::class, 'summary']);
            Route::get('/by-industry', [DashboardStatsController::class, 'byIndustry']);
            Route::get('/by-title-tier', [DashboardStatsController::class, 'byTitleTier']);
            Route::get('/by-status', [DashboardStatsController::class, 'byStatus']);
            Route::get('/by-country', [DashboardStatsController::class, 'byCountry']);
            Route::get('/timeline', [DashboardStatsController::class, 'timeline']);
        });

        // ── Webhooks: Ingest (supports both /webhooks and /webhook) ──
        Route::middleware('scope:webhooks:post')->group(function () {
            foreach (['webhooks', 'webhook'] as $prefix) {
                Route::prefix($prefix)->group(function () {
                    Route::post('/leads', [WebhookController::class, 'store']);
                    Route::post('/leads/bulk', [WebhookController::class, 'bulkStore']);
                    Route::post('/leads/bulk-delete', [WebhookController::class, 'bulkDelete']);
                    Route::post('/leads/bulk-tag', [WebhookController::class, 'bulkTag']);
                });
            }
        });

        // ── External Compatibility (aliases /external/* to unified endpoints) ──
        Route::prefix('external')->group(function () {
            Route::middleware('scope:leads:read')->group(function () {
                Route::get('/leads', [LeadController::class, 'index']);
                Route::get('/leads/filters', [LeadController::class, 'filters']);
                Route::get('/leads/{lead}', [LeadController::class, 'show']);
            });
            Route::middleware('scope:leads:export')->get('/leads/export/csv', [LeadController::class, 'exportCsv']);
            Route::middleware('scope:leads:write')->group(function () {
                Route::post('/leads', [LeadController::class, 'store']);
                Route::post('/leads/bulk', [LeadController::class, 'bulkStore']);
                Route::post('/leads/bulk-tag', [LeadController::class, 'bulkTag']);
            });
            Route::middleware('scope:leads:delete')->post('/leads/bulk-delete', [LeadController::class, 'bulkDelete']);
            Route::middleware('scope:tags:read')->get('/tags', [TagController::class, 'index']);
            Route::middleware('scope:stats:read')->get('/stats/summary', [DashboardStatsController::class, 'summary']);
        });

        // ── Developer Key Management (Sanctum auth required) ───
        Route::prefix('developer')->group(function () {
            Route::get('/api-keys', [DeveloperApiKeyController::class, 'index']);
            Route::post('/api-keys', [DeveloperApiKeyController::class, 'store']);
            Route::get('/api-keys/{id}', [DeveloperApiKeyController::class, 'show']);
            Route::patch('/api-keys/{id}', [DeveloperApiKeyController::class, 'update']);
            Route::delete('/api-keys/{id}', [DeveloperApiKeyController::class, 'destroy']);
            Route::post('/api-keys/{id}/rotate', [DeveloperApiKeyController::class, 'rotate']);

            // Trace logs
            Route::get('/traces', [ApiTraceController::class, 'index']);
            Route::get('/traces/{traceId}', [ApiTraceController::class, 'show']);
        });
    });
});
