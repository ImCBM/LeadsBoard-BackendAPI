# Leads Dashboard — Backend Service Implementation Plan

## Background

The current codebase is a fresh Laravel 12 scaffold with Sanctum auth, a generic `JobPosting` model, and placeholder services. **The actual domain is B2B lead management, not job postings.** We need to pivot the entire data model to match the real n8n payload (Full Name, Job Title, Title Tier, Corporate Email, Company, Industry, HQ Location, LinkedIn URLs, etc.) and build a robust backend that:

1. **Receives** lead data POSTed by n8n (webhook ingestion)
2. **Validates, normalizes, and deduplicates** incoming records
3. **Stores** them in MySQL (SQLite for local dev)
4. **Serves** a minimal Blade dashboard with filtering, search, counts, and CSV export
5. **Exposes** a clean REST API for future frontends
6. **Is Hostinger-ready** (MySQL, shared hosting considerations)

---

## User Review Required

> [!IMPORTANT]
> The existing `JobPosting`, `Company`, `City`, `Country` models and migrations will be **replaced** with a new `Lead`-centric schema that matches the actual n8n payload fields. The old scaffolded code was for a generic job board and doesn't match the real data.

> [!IMPORTANT]
> **Authentication strategy**: The plan uses Laravel Sanctum with two auth modes:
> - **API Token auth** for n8n webhook calls (static bearer token, no login flow needed)
> - **Session/cookie auth** for the admin dashboard (login page)
>
> This means n8n sends `Authorization: Bearer <token>` and the dashboard uses standard Laravel login.

> [!WARNING]
> The current database is SQLite. The plan will keep SQLite for local dev but include `.env.example` MySQL settings for Hostinger. A fresh `migrate:fresh` will be needed since we're replacing all domain tables.

---

## Open Questions

> [!IMPORTANT]
> **Rate limiting**: The plan includes 60 requests/minute for the webhook endpoint and 120/minute for the dashboard API. Are these limits acceptable, or does the n8n workflow batch large volumes that need a higher limit?

> [!NOTE]
> **Title Tier mapping**: The n8n docs mention Title Tier values of `C-Level`, `VP-Level`, `Director-Level`, `Other`. Should we accept any freeform string or enforce only these four values? (Plan assumes we enforce the four known values + `Other` as fallback.)

---

## Proposed Changes

### 1. Database Schema — New Migrations

We replace the old `job_postings`, `companies`, `cities`, `countries` tables with a single denormalized `leads` table that mirrors the n8n payload exactly plus metadata fields.

#### [NEW] `create_leads_table` migration

```
leads table:
├── id (bigint, PK)
├── full_name (string, 255)
├── job_title (string, 255, nullable)
├── title_tier (enum: C-Level, VP-Level, Director-Level, Other)
├── corporate_email (string, 255, unique) ← primary dedup key
├── email_status (string, 100, nullable)
├── company_name (string, 255)
├── clean_root_domain (string, 255, nullable)
├── website_status (string, 100, nullable)
├── executive_linkedin_url (string, 500, nullable)
├── company_linkedin_page (string, 500, nullable)
├── industry_classification (string, 255, nullable)
├── employee_headcount (integer, nullable)
├── hq_location (string, 500, nullable)
├── source (string, 50, default: 'n8n') ← tracks where the lead came from
├── status (enum: new, reviewed, qualified, rejected, default: 'new')
├── notes (text, nullable)
├── created_at / updated_at (timestamps)
```

Indexes: `corporate_email` (unique), `company_name`, `industry_classification`, `title_tier`, `status`, `created_at`.

#### [DELETE] Old migrations for `job_postings`, `companies`, `cities`, `countries`
#### [NEW] `add_role_to_users_table` migration — adds `role` enum (`admin`, `viewer`) to users table

---

### 2. Models

#### [NEW] [Lead.php](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/app/Models/Lead.php)
- Fillable fields matching all payload columns + `source`, `status`, `notes`
- Scopes: `byIndustry()`, `byTitleTier()`, `byStatus()`, `search()`, `dateRange()`
- Cast `employee_headcount` to integer, `created_at` to datetime

#### [DELETE] [JobPosting.php](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/app/Models/JobPosting.php), [Company.php](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/app/Models/Company.php), [City.php](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/app/Models/City.php), [Country.php](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/app/Models/Country.php)

#### [MODIFY] [User.php](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/app/Models/User.php)
- Add `role` field to fillable, add `isAdmin()` helper method

---

### 3. Services

#### [NEW] [LeadIngestionService.php](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/app/Services/LeadIngestionService.php)
- Maps n8n payload field names (e.g., `"Full Name"` → `full_name`) to database columns
- Normalizes: trim whitespace, Title Case names, sanitize URLs, parse headcount to integer
- Checks duplicates by `corporate_email`
- Returns structured result: `{ success: bool, lead: Lead|null, errors: [], duplicate: bool }`

#### [NEW] [LeadExportService.php](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/app/Services/LeadExportService.php)
- Generates CSV from filtered lead query
- Streams response for large datasets (no memory issues)

#### [MODIFY] [N8nService.php](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/app/Services/N8nService.php)
- Keep as-is for future outbound webhook calls

#### [DELETE] [JobPostingService.php](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/app/Services/JobPostingService.php), [GeocodingService.php](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/app/Services/GeocodingService.php)

---

### 4. Form Requests (Validation)

#### [NEW] [StoreLeadRequest.php](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/app/Http/Requests/StoreLeadRequest.php)
- Validates the n8n payload: `Full Name` required, `Corporate Work Email` required + email + unique, `Company Name` required, etc.
- Maps human-readable field names to snake_case for error messages

#### [NEW] [UpdateLeadRequest.php](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/app/Http/Requests/UpdateLeadRequest.php)
- For dashboard CRUD updates

#### [NEW] [BulkStoreLeadRequest.php](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/app/Http/Requests/BulkStoreLeadRequest.php)
- Validates array of leads for batch import

---

### 5. Controllers

#### [NEW] [WebhookController.php](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/app/Http/Controllers/Api/WebhookController.php)
- `POST /api/v1/webhook/leads` — single lead ingestion (n8n calls this)
- `POST /api/v1/webhook/leads/bulk` — batch ingestion (array of leads)
- Returns structured JSON response with status, inserted count, duplicate count, errors

#### [NEW] [LeadController.php](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/app/Http/Controllers/Api/LeadController.php)
- Full CRUD API resource for leads
- `GET /api/v1/leads` — paginated list with filtering (industry, title_tier, status, date range, search)
- `GET /api/v1/leads/{id}` — single lead detail
- `PUT /api/v1/leads/{id}` — update a lead (status, notes, etc.)
- `DELETE /api/v1/leads/{id}` — soft-delete or hard-delete
- `GET /api/v1/leads/export/csv` — CSV download with current filters applied

#### [NEW] [DashboardStatsController.php](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/app/Http/Controllers/Api/DashboardStatsController.php)
- `GET /api/v1/stats/summary` — total leads, today's count, this week, this month
- `GET /api/v1/stats/by-industry` — lead count grouped by industry
- `GET /api/v1/stats/by-title-tier` — lead count grouped by title tier
- `GET /api/v1/stats/by-status` — lead count grouped by status
- `GET /api/v1/stats/timeline` — leads over time (daily/weekly/monthly)

#### [NEW] [AuthController.php](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/app/Http/Controllers/Api/AuthController.php)
- `POST /api/v1/auth/login` — returns Sanctum token
- `POST /api/v1/auth/logout` — revokes token
- `GET /api/v1/auth/me` — returns authenticated user info

#### [NEW] [DashboardController.php](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/app/Http/Controllers/DashboardController.php)
- Serves the minimal Blade dashboard views (session-based auth)
- `GET /dashboard` — main leads table view
- `GET /login` — login page

#### [DELETE] [JobPostingController.php](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/app/Http/Controllers/Api/JobPostingController.php)

---

### 6. Middleware & Security

#### [NEW] [ValidateWebhookToken.php](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/app/Http/Middleware/ValidateWebhookToken.php)
- Validates `Authorization: Bearer <WEBHOOK_SECRET>` or `X-Webhook-Token` header
- The token is stored in `.env` as `WEBHOOK_SECRET`
- This is separate from Sanctum — it's a simple static token for n8n (no user context needed)

#### Rate Limiting
- Configure in `RouteServiceProvider` or `bootstrap/app.php`:
  - Webhook routes: 60/min
  - API routes: 120/min

#### CORS
- Configured to allow future frontend domains

---

### 7. Routes

#### [MODIFY] [api.php](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/routes/api.php)

```
Route prefix: /api/v1

Webhook routes (webhook token auth):
  POST /webhook/leads         → WebhookController@store
  POST /webhook/leads/bulk    → WebhookController@bulkStore

Auth routes (public):
  POST /auth/login            → AuthController@login

Authenticated routes (Sanctum):
  POST /auth/logout           → AuthController@logout
  GET  /auth/me               → AuthController@me
  
  GET    /leads               → LeadController@index
  GET    /leads/export/csv    → LeadController@exportCsv
  GET    /leads/{lead}        → LeadController@show
  POST   /leads               → LeadController@store
  PUT    /leads/{lead}        → LeadController@update
  DELETE /leads/{lead}        → LeadController@destroy
  
  GET  /stats/summary         → DashboardStatsController@summary
  GET  /stats/by-industry     → DashboardStatsController@byIndustry
  GET  /stats/by-title-tier   → DashboardStatsController@byTitleTier
  GET  /stats/by-status       → DashboardStatsController@byStatus
  GET  /stats/timeline        → DashboardStatsController@timeline
```

#### [MODIFY] [web.php](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/routes/web.php)
```
GET  /             → redirect to /dashboard
GET  /login        → DashboardController@showLogin
POST /login        → DashboardController@login
POST /logout       → DashboardController@logout
GET  /dashboard    → DashboardController@index (auth middleware)
```

---

### 8. Minimal Dashboard UI (Blade)

#### [NEW] `resources/views/dashboard/index.blade.php`
- Data table showing leads with all key columns
- Filter bar: industry dropdown, title tier dropdown, status dropdown, date range, search input
- Summary stat cards at top (total leads, today, this week, this month)
- CSV export button
- Pagination
- Uses the existing design system colors/typography from `JobBoard-design.md`

#### [NEW] `resources/views/dashboard/login.blade.php`
- Simple login form

#### [MODIFY] `resources/views/components/layout.blade.php`
- Add sidebar navigation for dashboard context
- Add CSRF meta tag
- Add dashboard-specific styles

---

### 9. Configuration & Environment

#### [MODIFY] [.env.example](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/.env.example)
- Add `WEBHOOK_SECRET=` (for n8n token auth)
- Add commented MySQL settings for Hostinger
- Add `LEADS_PER_PAGE=25`

#### [MODIFY] [.env](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/.env)
- Add `WEBHOOK_SECRET=local-dev-webhook-secret-change-me`
- Add `LEADS_PER_PAGE=25`

---

### 10. Database Seeder

#### [NEW] [LeadSeeder.php](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/database/seeders/LeadSeeder.php)
- Seeds the 30 sample leads from `sample_lead_list.csv`

#### [NEW] [AdminUserSeeder.php](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/database/seeders/AdminUserSeeder.php)
- Creates default admin: `admin@jobboard.local` / `password`

#### [MODIFY] [DatabaseSeeder.php](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/database/seeders/DatabaseSeeder.php)
- Calls `AdminUserSeeder` and `LeadSeeder`

---

### 11. API Documentation

#### [NEW] [docs/API_DOCUMENTATION.md](file:///c:/Works/CertiCodeThings/JobBoard-Laravel/docs/API_DOCUMENTATION.md)
- Complete endpoint reference with:
  - Authentication methods (webhook token vs Sanctum)
  - Every endpoint: method, URL, headers, request body, response format, status codes
  - Example payloads for n8n integration
  - Error response format
  - Filtering/pagination query parameters

---

## File Summary

| Action | File | Purpose |
|--------|------|---------|
| NEW | Migration: `create_leads_table` | Core leads table |
| NEW | Migration: `add_role_to_users_table` | Admin/viewer roles |
| DELETE | Migration: old `job_postings`, `companies`, `cities`, `countries` | No longer needed |
| NEW | `app/Models/Lead.php` | Lead model with scopes |
| DELETE | `app/Models/JobPosting.php`, `Company.php`, `City.php`, `Country.php` | Replaced by Lead |
| MODIFY | `app/Models/User.php` | Add role field |
| NEW | `app/Services/LeadIngestionService.php` | Normalize, validate, dedup |
| NEW | `app/Services/LeadExportService.php` | CSV streaming export |
| DELETE | `app/Services/JobPostingService.php`, `GeocodingService.php` | Replaced |
| NEW | `app/Http/Requests/StoreLeadRequest.php` | Webhook validation |
| NEW | `app/Http/Requests/UpdateLeadRequest.php` | Dashboard update validation |
| NEW | `app/Http/Requests/BulkStoreLeadRequest.php` | Batch validation |
| NEW | `app/Http/Controllers/Api/WebhookController.php` | n8n ingestion endpoint |
| NEW | `app/Http/Controllers/Api/LeadController.php` | CRUD API |
| NEW | `app/Http/Controllers/Api/DashboardStatsController.php` | Stats endpoints |
| NEW | `app/Http/Controllers/Api/AuthController.php` | Token auth |
| NEW | `app/Http/Controllers/DashboardController.php` | Blade dashboard |
| DELETE | `app/Http/Controllers/Api/JobPostingController.php` | Replaced |
| NEW | `app/Http/Middleware/ValidateWebhookToken.php` | Webhook security |
| MODIFY | `routes/api.php` | All new API routes |
| MODIFY | `routes/web.php` | Dashboard routes |
| NEW | `resources/views/dashboard/index.blade.php` | Leads table UI |
| NEW | `resources/views/dashboard/login.blade.php` | Login UI |
| MODIFY | `resources/views/components/layout.blade.php` | Dashboard layout |
| NEW | `database/seeders/LeadSeeder.php` | Sample data from CSV |
| NEW | `database/seeders/AdminUserSeeder.php` | Default admin user |
| MODIFY | `database/seeders/DatabaseSeeder.php` | Call new seeders |
| MODIFY | `.env` / `.env.example` | Webhook secret, config |
| NEW | `docs/API_DOCUMENTATION.md` | Full API reference |

---

## Verification Plan

### Automated Tests
```bash
php artisan migrate:fresh --seed
php artisan test
```

### Manual Verification
1. **Webhook ingestion**: `curl -X POST /api/v1/webhook/leads` with sample JSON payload → verify 201 response and DB record
2. **Duplicate rejection**: POST same email twice → verify 409 response
3. **Bulk import**: POST array of leads → verify counts
4. **Dashboard**: Visit `/dashboard` → verify table renders with seeded data
5. **Filtering**: Apply industry/tier/search filters → verify results
6. **CSV export**: Click export → verify downloaded CSV contains filtered data
7. **Auth**: Login with admin credentials → verify session; call API with token → verify access
8. **Rate limiting**: Rapid-fire requests → verify 429 after limit
