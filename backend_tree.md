```text
LeadsBoard-BackendAPI/
├── app/ — Core Application Logic
│   ├── Console/Commands/
│   │   └── SimulateN8nCommand.php — CLI tool to replay CSV data against webhooks (php artisan n8n:simulate)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── AuthController.php — Sanctum token issuance (login/logout/me) for SPA clients
│   │   │   │   ├── DashboardStatsController.php — Metrics aggregation (by industry, country, tier, timeline)
│   │   │   │   ├── LeadController.php — REST CRUD, multi-token search, relational sorting, CSV export
│   │   │   │   └── WebhookController.php — Single and bulk webhook entrypoint for automated pipelines
│   │   │   ├── ApiKeyController.php — Web-based API Key CRUD for the internal dashboard
│   │   │   ├── DashboardController.php — Minimal Blade web dashboard serving leads and stats
│   │   │   └── Controller.php — Base abstract Laravel controller
│   │   ├── Middleware/
│   │   │   ├── ValidateWebhookToken.php — Timing-safe verification of WEBHOOK_SECRET on webhook endpoints
│   │   │   └── ValidateApiKey.php — SHA-256 hashed API key validation with per-key rate limiting
│   │   └── Requests/
│   │       ├── BulkStoreLeadRequest.php — Validation rules for batch ingestion payloads (max 500)
│   │       ├── StoreLeadRequest.php — Validation rules for single lead payloads (n8n & snake_case)
│   │       └── UpdateLeadRequest.php — Validation rules for updating existing lead records
│   ├── Models/ — Eloquent Domain Models (3NF)
│   │   ├── Lead.php — Central prospect model: search scopes, computed accessors, constants
│   │   ├── Company.php — Business entity model (domain, LinkedIn, employee headcount)
│   │   ├── Industry.php — Normalized industry taxonomy table
│   │   ├── Location.php — Physical geography table (city, state_region, raw_location)
│   │   ├── Country.php — Normalized country reference table
│   │   ├── ApiKey.php — External API integration keys (hashed keys, rate limits, expiry)
│   │   └── User.php — Authentication user model utilizing Laravel Sanctum HasApiTokens
│   ├── Providers/
│   │   └── AppServiceProvider.php — Core service provider boot routines
│   └── Services/ — Encapsulated Business Services
│       ├── LeadIngestionService.php — Ingestion pipeline: mapping, normalization, dedup, atomic transactions
│       ├── LeadExportService.php — High-efficiency chunked StreamedResponse CSV generator with UTF-8 BOM
│       └── N8nService.php — Outbound HTTP client for dispatching events back to n8n
├── bootstrap/
│   └── app.php — Laravel 12 application bootstrapper and routing configurator
├── config/ — Application Configuration Files
│   ├── cors.php — CORS origins configuration (Vercel, Render wildcards, localhost)
│   ├── leads.php — Custom configuration for pagination defaults (LEADS_PER_PAGE)
│   ├── sanctum.php — Sanctum stateful domains and token expiration parameters
│   └── services.php — External service configurations (WEBHOOK_SECRET, default rate limits)
├── database/
│   ├── database.sqlite — Local development SQLite database storage file
│   ├── migrations/ — 11 Database schema migration files
│   └── seeders/
│       ├── DatabaseSeeder.php — Master seeder running AdminUserSeeder and LeadSeeder
│       ├── AdminUserSeeder.php — Default admin user generator (admin@leadsboard.local)
│       ├── LeadSeeder.php — 30 structured realistic B2B test leads
│       └── CsvImportSeeder.php — Dynamic CSV importer using the LeadIngestionService
├── docker/ — Container & Deployment Assets
│   ├── apache.conf — Custom Apache VirtualHost for containerized public/ execution
│   └── entrypoint.sh — Startup script: permissions, migrations, admin seeding, cache prep
├── docs/ — Technical specifications, sample datasets, and screenshots
├── routes/
│   ├── api.php — All API v1 routes: /webhook, /auth, /leads, /stats, /external
│   └── web.php — Web session routes: /login, /dashboard, /dashboard/api-keys
├── tests/
│   ├── Feature/ — 7 End-to-end HTTP feature tests
│   └── Unit/ — 3 Unit test suites (Ingestion, Models, Architecture)
├── Dockerfile — PHP 8.2-Apache multi-stage production container build definition
├── render.yaml — Infrastructure-as-Code blueprint for Render web service & PostgreSQL
├── composer.json — PHP dependencies, script runners (setup, dev, test), autoloader rules
├── phpunit.xml — Testing configuration specifying SQLite :memory: database execution
└── .env.example — Exhaustive environment variable configuration template
```