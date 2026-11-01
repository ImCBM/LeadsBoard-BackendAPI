# LeadsBoard Backend API Manual
**ENTERPRISE ENGINEERING & OPERATIONS MANUAL**

*B2B Lead Pipeline Backend API — Architecture, Implementation, Operations, Security & Integration Guide*

---

| METRIC / PROPERTY | SPECIFICATION |
| :--- | :--- |
| **Framework & Engine** | Laravel 12 (PHP 8.2+) |
| **Authentication Layer** | Sanctum + API Keys + Webhooks |
| **Database Model** | 3NF Relational (SQLite / MySQL / PG) |
| **Document Purpose** | Starter Guide & Technical Manual |
| **Deployment Scope** | Local, Docker, Render, cPanel |
| **Document Version** | v1.0.0 (Production Release) |

---

## 📑 Table of Contents

- [1. Executive Summary & Project Fundamentals](#1-executive-summary--project-fundamentals)
  - [1.1 What Is LeadsBoard?](#11-what-is-leadsboard)
  - [1.2 Technology Stack & Prerequisites](#12-technology-stack--prerequisites)
- [2. System Architecture & Directory Blueprint](#2-system-architecture--directory-blueprint)
  - [2.1 End-to-End Data Pipeline](#21-end-to-end-data-pipeline)
  - [2.2 Comprehensive Directory & File Structure](#22-comprehensive-directory--file-structure)
  - [2.3 Third Normal Form (3NF) Database Schema](#23-third-normal-form-3nf-database-schema)
- [3. Getting Started: Initialization & Setup](#3-getting-started-initialization--setup)
  - [3.1 Step-by-Step Installation](#31-step-by-step-installation)
  - [3.2 Running the Development Stack](#32-running-the-development-stack)
  - [3.3 Connecting Frontend Clients (Crucial Gotchas)](#33-connecting-frontend-clients-crucial-gotchas)
- [4. Configuration & Environment Variables Reference](#4-configuration--environment-variables-reference)
  - [Complete .env Key Directory](#complete-env-key-directory)
  - [Production Key Generation Commands](#production-key-generation-commands)
- [5. Authentication Architecture: Three-Tier Security](#5-authentication-architecture-three-tier-security)
  - [5.1 Tier 1: Webhook Authentication (n8n Automation)](#51-tier-1-webhook-authentication-n8n-automation)
  - [5.2 Tier 2: Dynamic API Keys & Rate Limiting (External Services)](#52-tier-2-dynamic-api-keys--rate-limiting-external-services)
  - [5.3 Tier 3: Laravel Sanctum Authentication (React Frontend SPA)](#53-tier-3-laravel-sanctum-authentication-react-frontend-spa)
- [6. Complete REST API Reference](#6-complete-rest-api-reference)
  - [6.1 Webhook Ingestion Endpoints](#61-webhook-ingestion-endpoints)
  - [6.2 Authentication Endpoints](#62-authentication-endpoints)
  - [6.3 Leads CRUD, Multi-Token Search & Filtering](#63-leads-crud-multi-token-search--filtering)
  - [6.4 Dashboard Analytics Endpoints](#64-dashboard-analytics-endpoints)
- [7. Core Systems & Services Deep Dive](#7-core-systems--services-deep-dive)
  - [7.1 Lead Ingestion Engine (`LeadIngestionService.php`)](#71-lead-ingestion-engine-leadingestionservicephp)
  - [7.2 Streamed CSV Export Engine (`LeadExportService.php`)](#72-streamed-csv-export-engine-leadexportservicephp)
  - [7.3 Artisan n8n Webhook Simulator (`SimulateN8nCommand.php`)](#73-artisan-n8n-webhook-simulator-simulaten8ncommandphp)
  - [7.4 Built-In Web Dashboard & API Key Management](#74-built-in-web-dashboard--api-key-management)
- [8. Testing Manual: Unit, Feature, Postman & cURL](#8-testing-manual-unit-feature-postman--curl)
  - [8.1 PHPUnit Automated Test Suite](#81-phpunit-automated-test-suite)
  - [8.2 Terminal cURL Testing Recipes](#82-terminal-curl-testing-recipes)
  - [8.3 Postman Integration & Testing Manual](#83-postman-integration--testing-manual)
  - [8.4 End-to-End Integration Testing Checklist](#84-end-to-end-integration-testing-checklist)
- [9. Error Catalog & Troubleshooting Guide](#9-error-catalog--troubleshooting-guide)
  - [9.1 HTTP Status Code Reference Catalog](#91-http-status-code-reference-catalog)
  - [9.2 Common Platform Quirks & Remediation](#92-common-platform-quirks--remediation)
- [10. Security, Data Integrity & Hardening](#10-security-data-integrity--hardening)
  - [10.1 Cryptographic Standards & Timing-Attack Defense](#101-cryptographic-standards--timing-attack-defense)
  - [10.2 Relational Integrity & Transactional Guarantees](#102-relational-integrity--transactional-guarantees)
  - [10.3 Production Hardening Checklist](#103-production-hardening-checklist)
- [11. Universal Deployment Guide](#11-universal-deployment-guide)
  - [11.1 Docker & Render.com Cloud Native Deployment](#111-docker--rendercom-cloud-native-deployment)
  - [11.2 Shared Hosting Deployment (Hostinger / cPanel)](#112-shared-hosting-deployment-hostinger--cpanel)
- [12. Scalability Roadmap & Future Architecture](#12-scalability-roadmap--future-architecture)

---

## 1. Executive Summary & Project Fundamentals

### 1.1 What Is LeadsBoard?

**LeadsBoard Backend API** is an enterprise-grade B2B lead ingestion, enrichment, and pipeline management API built with **Laravel 12** on **PHP 8.2+**. It functions as the central data consolidation hub in a modern demand-generation and sales prospecting architecture.

In typical B2B sales development environments, lead data is gathered across disparate sources: automated web scraping pipelines (e.g., n8n workflows monitoring job boards or LinkedIn), third-party enrichment vendors, manual SDR data entry, and bulk CSV uploads. Without a central data governor, datasets suffer from duplicate email entries, unstandardized job titles, malformed URLs, inconsistent geographical notations, and unstructured data tables.

**LeadsBoard solves this problem by acting as an authoritative ingestion gatekeeper.** It ingests leads via high-throughput webhooks, runs comprehensive data normalization routines, deduplicates prospects against corporate email records, normalizes entity relations into a Third Normal Form (3NF) schema, and exposes rich, filtered REST endpoints for client applications and downstream automations.

#### 🎯 Core Capabilities

- **Automated Webhook Ingestion:** Single & bulk endpoints accepting n8n and external automation payloads.
- **Data Cleansing:** Automated title tier classification, Title Case name formatting, domain stripping, URL validation.
- **Zero-Duplicate Guarantee:** Email-level uniqueness verification before database entry.
- **3NF Relational Modeling:** Separate, linked records for Companies, Industries, Locations, and Countries.
- **⚡ High-Performance Serving:**
  - **Multi-Token Search:** Sub-second filtering across 9+ simultaneous relational columns.
  - **Streamed CSV Export:** UTF-8 BOM exports supporting 100k+ rows with minimal memory footprint.
  - **Three-Tier Auth:** Dedicated protection layers for webhooks, external integrations, and web users.
  - **Built-In Web Dashboard:** Lightweight Blade UI for key management and standalone administration.

---

### 1.2 Technology Stack & Prerequisites

| COMPONENT | TECHNOLOGY | VERSION | PURPOSE |
| :--- | :--- | :--- | :--- |
| **Framework** | Laravel | 12.x | Core application foundation, routing, ORM, validation, and container. |
| **Language** | PHP | 8.2+ | Engine (Required extensions: `pdo`, `pdo_sqlite`, `pdo_mysql`, `mbstring`, `bcmath`, `zip`, `opcache`). |
| **Database (Dev)** | SQLite | 3.x | Embedded, zero-configuration local database (`database/database.sqlite`). |
| **Database (Prod)** | MySQL / PostgreSQL | 8.0+ / 14+ | Production enterprise relational databases supported natively via PDO drivers. |
| **Authentication** | Laravel Sanctum | 4.x | API token issuance, session validation, and Bearer token verification. |
| **Web Server** | Apache / Nginx / Built-in | 2.4+ | HTTP request routing and proxying (Docker image uses Apache). |

---

## 2. System Architecture & Directory Blueprint

### 2.1 End-to-End Data Pipeline

```text
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ INGESTION SOURCES                                                                      │
│ ┌─────────────────────┐ ┌─────────────────────┐ ┌────────────────────────────┐         │
│ │ n8n Webhook Workflow│ │ 3rd-Party App / API │ │ CSV Import Seeder / File   │         │
│ └──────────┬──────────┘ └──────────┬──────────┘ └─────────────┬──────────────┘         │
└─────────────┼──────────────────────────┼─────────────────────────────┼─────────────────┘
              │ POST /webhook/leads      │ POST /api/v1/leads          │ artisan db:seed
              ▼                          ▼                             ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ AUTHENTICATION & GATEKEEPING                                                           │
│ ValidateWebhookToken (Bearer Token) │ auth:sanctum / ValidateApiKey (Hashed Key)       │
└─────────────────────────────────────────┬──────────────────────────────────────────────┘
                                          │ FormRequest Validation (StoreLeadRequest)
                                          ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ LEAD INGESTION ENGINE                                                                  │
│ app/Services/LeadIngestionService.php                                                  │
│ 1. Field Mapping: n8n human-readable keys ("Full Name") -> snake_case                  │
│ 2. Normalization: Title Case names, lowercased emails, cleaned root domains            │
│ 3. Title Tier Classification: C-Level, VP-Level, Director-Level, Other                 │
│ 4. Geolocation Extraction: Country parsed from HQ Location string                      │
│ 5. Deduplication Gate: Check corporate_email (abort on duplicate: 409 Conflict)        │
└─────────────────────────────────────────┬──────────────────────────────────────────────┘
                                          │ DB::transaction() - Atomic 3NF Insert
                                          ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ RELATIONAL DATABASE (3NF)                                                              │
│ ┌───────────────┐       ┌───────────────┐       ┌───────────────┐   ┌───────────┐      │
│ │  industries   │◄──────┤   companies   │◄──────┤     leads     │   │ api_keys  │      │
│ └───────────────┘       └───────┬───────┘       └───────────────┘   └───────────┘      │
│                                 │                                   ┌───────────┐      │
│ ┌───────────────┐       ┌───────▼───────┐                           │   users   │      │
│ │   countries   │◄──────┤   locations   │                           └───────────┘      │
│ └───────────────┘       └───────────────┘                                              │
└─────────────────────────────────────────┬──────────────────────────────────────────────┘
                                          │ JSON Responses / Streamed CSV Export
                                          ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ CONSUMERS & CLIENTS                                                                    │
│ ┌─────────────────────────┐   ┌──────────────────────────┐   ┌─────────────────────┐   │
│ │ React SPA (Vite/Vercel) │   │  3rd-Party Partner APIs  │   │ Blade Web Dashboard │   │
│ └─────────────────────────┘   └──────────────────────────┘   └─────────────────────┘   │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

---

### 2.2 Comprehensive Directory & File Structure

Below is the complete architectural layout of the backend codebase with directory classifications and file responsibilities:

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

---

### 2.3 Third Normal Form (3NF) Database Schema

To prevent data redundancy and ensure relational integrity, LeadsBoard normalizes prospect records into distinct relational entities. When multiple contacts work at the same company, the company information (domain, website status, headcount, LinkedIn URL) is stored once in the `companies` table.

| TABLE | PRIMARY KEY | FOREIGN KEYS | INDEXES | PURPOSE & COLUMNS |
| :--- | :--- | :--- | :--- | :--- |
| **`leads`** | `id` (BIGINT) | `company_id`<br>*(Nullable, FK → `companies.id`, ON DELETE SET NULL)* | `full_name`, `title_tier`, `status`, `ingestion_channel`, `created_at` | Central prospect record.<br>**Columns:** `full_name`, `job_title`, `title_tier`, `corporate_email` (UNIQUE), `email_status`, `executive_linkedin_url`, `ingestion_channel`, `status`, `notes`, `timestamps`. |
| **`companies`** | `id` (BIGINT) | `industry_id`<br>*(FK → `industries.id`)*<br>`location_id`<br>*(FK → `locations.id`)* | `name`, `employee_headcount` | Corporate entity.<br>**Columns:** `name`, `clean_root_domain` (UNIQUE), `website_status`, `company_linkedin_page`, `employee_headcount`, `timestamps`. |
| **`industries`** | `id` (BIGINT) | None | `name` (UNIQUE) | Normalized industry taxonomy (e.g., "Software", "Real Estate", "Financial Services"). |
| **`locations`** | `id` (BIGINT) | `country_id`<br>*(FK → `countries.id`)* | `city`, `state_region` | Normalized geographical location.<br>**Columns:** `raw_location` (UNIQUE), `city`, `state_region`, `timestamps`. |
| **`countries`** | `id` (BIGINT) | None | `name` (UNIQUE) | Normalized country catalog (e.g., "Portugal", "Denmark", "United States"). |
| **`api_keys`** | `id` (BIGINT) | None | `key` (UNIQUE) | API key registry.<br>**Columns:** `name`, `key` (SHA-256 hash), `plain_text_prefix` (8 chars), `rate_limit_per_minute`, `is_active`, `last_used_at`, `expires_at`. |
| **`users`** | `id` (BIGINT) | None | `email` (UNIQUE) | Administrative accounts.<br>**Columns:** `name`, `email`, `password` (bcrypt), `role` (e.g. "admin"), `remember_token`. |

> [!TIP]
> **Virtual Model Attributes (Backward Compatibility Accessors)**  
> The `Lead` Eloquent model exposes virtual accessors (`company_name`, `clean_root_domain`, `website_status`, `company_linkedin_page`, `industry_classification`, `employee_headcount`, `hq_location`, `country`). These are appended to all serialized JSON responses via `$appends` and eager-loaded via `$with = ['company.industry', 'company.location.country']`, guaranteeing flat, easy consumption for API clients while maintaining 3NF purity in storage.

---

## 3. Getting Started: Initialization & Setup

### 3.1 Step-by-Step Installation

Follow these exact steps to set up, configure, and boot the LeadsBoard backend in a local development environment:

```bash
# 1. Clone the repository into your development directory
$ git clone <your-repo-url>
$ cd LeadsBoard-BackendAPI

# 2. Install PHP dependencies via Composer
$ composer install

# 3. Create your local environment configuration from template
$ cp .env.example .env

# 4. Generate the Laravel application cryptographic key
$ php artisan key:generate

# 5. Initialize the SQLite database file
# On Linux / macOS:
$ touch database/database.sqlite
# On Windows PowerShell:
$ New-Item -ItemType File -Path database\database.sqlite -Force

# 6. Run database migrations and seed sample administrative & lead records
$ php artisan migrate --seed

# 7. Start the local development server on 127.0.0.1:8000
$ php artisan serve
```

#### ⚡ One-Command Setup Script

You can execute the entire sequence automatically using the bundled Composer setup script:

```bash
composer run setup
```

---

### 3.2 Running the Development Stack

| MODE | COMMAND | WHAT IT EXECUTES |
| :--- | :--- | :--- |
| **Standard Server** | `php artisan serve` | Spins up the single PHP CLI web server listening on `http://127.0.0.1:8000`. |
| **Full Concurrency Dev Stack** | `composer run dev` | Launches four concurrent background processes:<br>• `php artisan serve` (API Server on port 8000)<br>• `php artisan queue:listen` (Asynchronous job queue runner)<br>• `php artisan pail` (Real-time tailing log stream)<br>• `npm run dev` (Vite asset watcher for Blade views) |

---

### 3.3 Connecting Frontend Clients (Crucial Gotchas)

> [!WARNING]
> **Network Configuration & IPv6 Traps**  
> When connecting the React frontend (or any external client) to the backend locally, note these critical considerations:
>
> - **IPv4 vs. IPv6 Resolution:** Modern Node.js versions (v18+) prioritize IPv6 (`[::1]`) when resolving the hostname `localhost`. Because PHP's built-in `artisan serve` binds strictly to IPv4 `127.0.0.1`, requests to `http://localhost:8000` will trigger intermittent `ERR_CONNECTION_REFUSED` or `Network Error`. **Always use explicit IPv4:**  
>   `VITE_API_BASE_URL=http://127.0.0.1:8000`
>
> - **CORS Preflight:** Laravel handles HTTP `OPTIONS` requests automatically via `config/cors.php`. If you run the frontend on a custom port (e.g. `http://localhost:3000`), ensure that port is listed in `CORS_ALLOWED_ORIGINS`.

---

## 4. Configuration & Environment Variables Reference

All application behavior, credentials, security tokens, and database connections are governed by environment variables in `.env`. Below is the complete reference catalog:

### Complete .env Key Directory

| VARIABLE NAME | DEFAULT VALUE | TYPE | DESCRIPTION & OPERATIONAL USAGE |
| :--- | :--- | :--- | :--- |
| `APP_NAME` | `LeadsBoard` | String | The application name. Appears in outgoing notifications and UI headers. |
| `APP_ENV` | `local` | Enum | Environment mode: `local`, `testing`, or `production`. |
| `APP_KEY` | `Generated` | String | **Critical:** 32-character AES-256 encryption key. Generated via `php artisan key:generate`. |
| `APP_DEBUG` | `true` | Boolean | Detailed stack traces on exceptions. **Must be `false` in production** to prevent credential leaks. |
| `APP_URL` | `http://127.0.0.1` | URL | Primary canonical URL of the application. Set to your production domain in deployment. |
| `APP_PORT` | `8000` | Integer | HTTP listener port for local development and simulator port resolution. |
| `DB_CONNECTION` | `sqlite` | String | Database driver: `sqlite` for local development, `mysql` or `pgsql` for production. |
| `DB_HOST` | `127.0.0.1` | String | Database host address (MySQL/PostgreSQL). |
| `DB_PORT` | `3306` | Integer | Database listener port (3306 for MySQL, 5432 for PostgreSQL). |
| `DB_DATABASE` | `laravel` | String | Target database name. For SQLite, defaults to `database/database.sqlite`. |
| `DB_USERNAME` | `root` | String | Database authentication user. |
| `DB_PASSWORD` | `(empty)` | String | Database authentication password. |
| `DATABASE_URL` | `(empty)` | URL | Universal connection string (e.g. `postgres://user:pass@host:5432/db`). Used by cloud hosts like Render. |
| `WEBHOOK_SECRET` | `local-dev-webhook...` | String | **Critical:** Static secret used to authorize incoming n8n webhook HTTP requests. |
| `API_DEFAULT_RATE_LIMIT` | `120` | Integer | Default maximum allowed requests per minute for API Key authenticated requests. |
| `LEADS_PER_PAGE` | `25` | Integer | Default pagination page size when `per_page` query parameter is omitted. (Max limit: 100). |
| `CORS_ALLOWED_ORIGINS` | `http://localhost:5173...` | String | Comma-separated list of allowed origins. Wildcard patterns (`*.vercel.app`) configured in `config/cors.php`. |
| `N8N_SIMULATE_BASE_URL` | `(empty)` | URL | Target backend URL for the `php artisan n8n:simulate` command when testing against remote staging/production. |
| `RUN_MIGRATIONS` | `true` | Boolean | Docker entrypoint flag: automatically executes `php artisan migrate --force` on container boot. |
| `SEED_ADMIN` | `true` | Boolean | Docker entrypoint flag: automatically seeds default administrative account if missing. |

### 🔒 Production Key Generation Commands

Before promoting code to staging or production, generate fresh, secure cryptographic secrets:

```bash
# Generate Application Key:
php artisan key:generate --force

# Generate a 64-character cryptographically secure Webhook Secret:
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

---

## 5. Authentication Architecture: Three-Tier Security

LeadsBoard enforces a segregated, three-tier authentication strategy designed specifically for the distinct requirements of automated pipelines, external B2B partner integrations, and interactive web user interfaces:

| TIER | TARGET CONSUMER | PROTECTED ROUTES | MIDDLEWARE | AUTH CREDENTIAL TYPE |
| :--- | :--- | :--- | :--- | :--- |
| **Tier 1** | n8n & Upstream Automations | `/api/v1/webhook/*` | `ValidateWebhookToken` | Static Pre-Shared Secret (`WEBHOOK_SECRET`) |
| **Tier 2** | 3rd-Party Partner Integrations | `/api/v1/external/*` | `ValidateApiKey` | Dynamic API Key (SHA-256 Hashed, Per-Key Rate Limited) |
| **Tier 3** | React SPA Client / Users | `/api/v1/*` (Protected) | `auth:sanctum` | Personal Access Bearer Token (Sanctum) |
| **Web** | Admin Web Dashboard | `/dashboard/*` | `auth` (Session) | Standard Laravel Encrypted Cookies & Session Store |

---

### 5.1 Tier 1: Webhook Authentication (n8n Automation)

Automated ingestion pipelines (such as n8n workflows) push high-frequency lead batches into the system. They authenticate using a static Bearer secret configured in `.env` as `WEBHOOK_SECRET`.

- **Header Formats Supported:**
  - `Authorization: Bearer YOUR_CONFIGURED_WEBHOOK_SECRET`
  - `X-Webhook-Token: YOUR_CONFIGURED_WEBHOOK_SECRET`
- **Verification Mechanism:** Evaluated inside `App\Http\Middleware\ValidateWebhookToken` using PHP's native `hash_equals()` function, preventing timing attacks.
- **Fail-Safe:** If `WEBHOOK_SECRET` is unconfigured or blank, the endpoint rejects all requests with `503 Service Unavailable` rather than failing open.

---

### 5.2 Tier 2: Dynamic API Keys & Rate Limiting (External Services)

External integrations that need programmatic read access to leads and metrics use dynamic API keys managed via the backend dashboard (`/dashboard/api-keys`).

- **Hashed Storage:** The plain-text key (e.g. `jb_live_xxxxxxxxxxxxxxxxxxxxxxxx`) is presented to the administrator exactly once upon generation. The database stores only a SHA-256 cryptographic hash (`hash('sha256', $plainTextKey)`) and an 8-character prefix for identification.
- **Passing the Key:** Supported via `Authorization: Bearer <key>`, `X-API-Key: <key>` header, or `?api_key=<key>` query string.
- **Cached Lookups:** Verified keys are cached in memory for 300 seconds (5 minutes) to avoid redundant database lookups under high request volume.
- **Granular Rate Limiting:** Each key can have a custom `rate_limit_per_minute`:
  - `null` → Defaults to system setting (`API_DEFAULT_RATE_LIMIT`, default 120 req/min).
  - `0` → Unlimited requests (no rate limiting applied).
  - `N > 0` → Strictly capped at N requests per 60-second sliding window.
- **Rate Limit Headers:** When exceeded, returns HTTP `429 Too Many Requests` with standard RFC headers (`Retry-After`, `X-RateLimit-Limit`, `X-RateLimit-Remaining`).

---

### 5.3 Tier 3: Laravel Sanctum Authentication (React Frontend SPA)

The official React frontend interacts with the REST API using ephemeral personal access tokens issued via `POST /api/v1/auth/login`.

- Upon valid credential submission, existing tokens for that user are revoked (enforcing single-session cleanliness) and a new plain-text token is issued.
- The client attaches the token to all API calls: `Authorization: Bearer 1|xxxxxxxxxxxxxxxxxxxxxxxx`.
- Calling `POST /api/v1/auth/logout` deletes the current token in the database.

---

## 6. Complete REST API Reference

### 6.1 Webhook Ingestion Endpoints

#### `POST /api/v1/webhook/leads`

Ingests a single lead. Automatically maps field names, cleans domains, Title Cases names, normalizes title tiers, extracts country from HQ location, deduplicates on email, and creates 3NF relational records atomically.

**Authentication:** Webhook Token (`Authorization: Bearer <WEBHOOK_SECRET>`)

**Payload Schema (Accepts n8n human-readable or snake_case keys):**

```json
{
  "Full Name": "Frank Mortensen",
  "Job Title": "Deputy CEO",
  "Title Tier": "C-Level",
  "Corporate Work Email": "frank@sydbank.dk",
  "Email Status": "✅ Valid email",
  "Company Name": "AL Sydbank",
  "Clean Root Domain": "al-sydbank.dk",
  "Website Status": "HTTP 200 OK",
  "Executive LinkedIn URL": "https://www.linkedin.com/in/frank-mortensen62b972/",
  "Company LinkedIn Page": "https://www.linkedin.com/company/al-sydbank/",
  "Industry Classification": "Banking / Financial Services",
  "Employee Headcount": 53,
  "HQ Location": "Aabenraa, Southern Denmark, Denmark"
}
```

**Response Status Codes:**

- `201 Created` — Lead created successfully. Returns the full lead object with eager-loaded relations.
- `409 Conflict` — Duplicate email. `{"message": "Duplicate lead — this email already exists."}`
- `422 Unprocessable Content` — Validation error (e.g. missing required field or malformed email).
- `401 Unauthorized` — Missing or invalid webhook secret token.
- `503 Service Unavailable` — `WEBHOOK_SECRET` is unconfigured on the server.

---

#### `POST /api/v1/webhook/leads/bulk`

Ingests a batch array of up to 500 leads in a single HTTP request. Processes each record through the ingestion pipeline independently and returns individual statuses.

**Authentication:** Webhook Token (`Authorization: Bearer <WEBHOOK_SECRET>`)

**Request Body:**

```json
{
  "leads": [
    { ... },
    { ... }
  ]
}
```

**Response Codes:** `201 Created` (all successful) or `207 Multi-Status` (partial batch errors). Includes an insertion summary (`inserted`, `duplicates`, `errors`, `total`).

---

### 6.2 Authentication Endpoints

#### `POST /api/v1/auth/login`

Authenticates user credentials and issues a new Sanctum API token while deleting all previously issued tokens for that account.

- **Request:** `{"email": "admin@leadsboard.local", "password": "password"}`
- **Response (`200 OK`):** Returns `data.token` (plain-text Sanctum token) and `data.user` profile.

#### `POST /api/v1/auth/logout`

Revokes the calling token in the database.

- **Auth:** Sanctum Bearer Token

#### `GET /api/v1/auth/me`

Returns profile details for the authenticated token holder.

- **Auth:** Sanctum Bearer Token

---

### 6.3 Leads CRUD, Multi-Token Search & Filtering

#### `GET /api/v1/leads`

Returns a paginated list of leads with support for 12 simultaneous filter parameters, order-independent multi-token search, and relational column sorting.

**Authentication:** Sanctum Bearer Token (or API Key via `/api/v1/external/leads`)

**Supported Query Parameters:**

| PARAMETER | TYPE | EXAMPLE | FUNCTIONALITY |
| :--- | :--- | :--- | :--- |
| `search` | String | `John, Lisbon, Tech` | Order-independent, multi-token search across names, emails, companies, domains, titles, industries, locations, countries. Comma-delimited tokens apply `AND` logic. |
| `industry` | String | `Real Estate` | Exact match on linked `industries.name`. |
| `title_tier` | Enum | `C-Level` | Filter by: `C-Level`, `VP-Level`, `Director-Level`, `Other`. |
| `status` | Enum | `new` | Filter by: `new`, `reviewed`, `qualified`, `rejected`. |
| `country` | String | `Portugal, Spain` | Comma-separated country list (evaluates as SQL `WHERE IN`). |
| `ingestion_channel` | Enum | `n8n` | Filter by: `n8n`, `manual`, `api`, `csv_import`. |
| `headcount_range` | Enum | `51-200` | Presets: `1-10`, `11-50`, `51-200`, `201-500`, `501-1000`, `1000+`. |
| `headcount_min` | Integer | `50` | Explicit minimum headcount boundary. |
| `headcount_max` | Integer | `500` | Explicit maximum headcount boundary. |
| `website_status` | String | `200` | Filters active websites (`200` / `active`) vs offline/error sites (`error` / `offline`). |
| `email_status` | String | `valid` | Filters verified valid emails vs invalid/catch-all records. |
| `date_from` / `date_to` | Date | `2026-08-01` | Date range boundaries on `leads.created_at` (`YYYY-MM-DD`). |
| `sort_by` | String | `company_name` | Columns: `created_at`, `full_name`, `job_title`, `title_tier`, `corporate_email`, `company_name`, `clean_root_domain`, `website_status`, `industry_classification`, `country`, `employee_headcount`, `status`. |
| `sort_dir` | String | `asc` | Sort direction: `asc` or `desc` (default: `desc`). |
| `per_page` | Integer | `25` | Items per page (default: 25, maximum: 100). |

---

#### `GET /api/v1/leads/{id}`

Fetches a single lead by ID with all related company, location, and industry data.

#### `POST /api/v1/leads`

Creates a lead via direct API call (sets `ingestion_channel = 'api'`).

#### `PUT /api/v1/leads/{id}`

Updates lead and linked company records atomically in a DB transaction.

#### `DELETE /api/v1/leads/{id}`

Permanently deletes a prospect record from the database.

#### `GET /api/v1/leads/export/csv`

Streams all leads matching active filter parameters directly as a downloadable CSV with UTF-8 BOM encoding.

#### `GET /api/v1/leads/filters`

Returns distinct filter taxonomy values (industries, tiers, statuses, countries, channels) to populate frontend dropdowns.

---

### 6.4 Dashboard Analytics Endpoints

| METHOD | ENDPOINT | AUTHENTICATION | DESCRIPTION & DATA RETURNED |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/v1/stats/summary` | Sanctum / API Key | Aggregate counts: `total_leads`, `today`, `this_week`, `this_month`, and per-status breakdown (`new`, `reviewed`, `qualified`, `rejected`). |
| `GET` | `/api/v1/stats/by-industry` | Sanctum | Array of industries sorted by prospect count descending:<br>`[{"industry_classification": "Software", "count": 14}, ...]`. |
| `GET` | `/api/v1/stats/by-title-tier` | Sanctum | Prospect count grouped by organizational seniority (`C-Level`, `VP-Level`, `Director-Level`, `Other`). |
| `GET` | `/api/v1/stats/by-status` | Sanctum | Prospect count grouped by qualification status. |
| `GET` | `/api/v1/stats/by-country` | Sanctum | Prospect count grouped by country extracted from company headquarters. |
| `GET` | `/api/v1/stats/timeline?days=30` | Sanctum | Daily prospect count timeseries for trend visualization over the last N days (maximum 365). |

---

## 7. Core Systems & Services Deep Dive

### 7.1 Lead Ingestion Engine (`LeadIngestionService.php`)

The `App\Services\LeadIngestionService` class is the central orchestrator responsible for validating, normalizing, deduplicating, and persisting incoming lead data.

#### STEP 1: UNIVERSAL FIELD NAME MAPPING

Incoming payloads from n8n use capitalized, human-readable keys (e.g. `"Full Name"`, `"Corporate Work Email"`). Direct API calls use snake_case (e.g. `full_name`, `corporate_email`). The service maps both to internal keys using a bidirectional lookup table:

```php
private const FIELD_MAP = [
    'Full Name' => 'full_name',
    'Job Title' => 'job_title',
    'Title Tier' => 'title_tier',
    'Corporate Work Email' => 'corporate_email',
    'Email Status' => 'email_status',
    'Company Name' => 'company_name',
    'Clean Root Domain' => 'clean_root_domain',
    'Website Status' => 'website_status',
    'Executive LinkedIn URL' => 'executive_linkedin_url',
    'Company LinkedIn Page' => 'company_linkedin_page',
    'Industry Classification' => 'industry_classification',
    'Employee Headcount' => 'employee_headcount',
    'HQ Location' => 'hq_location',
];
```

#### STEP 2: DATA NORMALIZATION RULES

- **Full Name:** Converted to proper Title Case via `Str::title()`.
- **Email Address:** Trimmed and forced to lowercase via `Str::lower()`.
- **Root Domain:** Strips leading protocols (`http://`, `https://`), `www.` prefixes, and trailing slashes.
- **Title Tiering:** If missing or unclassified, analyzes `job_title` using keyword heuristics:
  - `C-Level`: CEO, CFO, CTO, COO, CMO, Founder, President, Owner, Chief, Partner.
  - `VP-Level`: VP, Vice President, SVP, EVP.
  - `Director-Level`: Director, Managing Director.
  - `Other`: Default fallback.
- **LinkedIn URLs:** Validated against PHP's `FILTER_VALIDATE_URL`. Malformed strings are safely sanitized to `null`.
- **Headcount Parsing:** Strips non-numeric characters (e.g. `"~50-100"` → `50100` or parsed integer).
- **Geolocation Parsing:** Extracts the country name from the final segment of comma-delimited location strings (e.g. `"Lisbon, Lisbon, Portugal"` → `"Portugal"`).

#### STEP 3: ATOMIC 3NF TRANSACTION EXECUTION

Once validated and deduplicated, the ingestion service wraps all entity creation within a `DB::transaction()` closure:

1. **Industry:** Resolves or creates record: `Industry::firstOrCreate(['name' => $industryName])`.
2. **Country:** Resolves or creates record: `Country::firstOrCreate(['name' => $countryName])`.
3. **Location:** Resolves or creates record linking to country:
   `Location::firstOrCreate(['raw_location' => $hq], ['country_id' => $country->id, ...])`.
4. **Company:** Deduplicated by `clean_root_domain` first, then by `name`. If an existing company is found, any missing attributes (LinkedIn URL, headcount, industry link) are enriched. If not found, a new `Company` record is created.
5. **Lead:** Creates the final `Lead` record referencing `company_id` with `status = 'new'`.

---

### 7.2 Streamed CSV Export Engine (`LeadExportService.php`)

Exporting large datasets via traditional in-memory generation (e.g. storing CSV strings in memory before returning) leads to fatal PHP memory exhaustion (`Allowed memory size exhausted`).

LeadsBoard implements a high-efficiency streaming export service via Symfony's `StreamedResponse`:

- **Chunked Processing:** Executes `$query->chunk(500, function ($leads) ...)`, fetching and flushing exactly 500 records at a time. Memory consumption remains flat at < 8 MB regardless of whether exporting 100 leads or 500,000 leads.
- **Microsoft Excel UTF-8 BOM:** Prepends the 3-byte Byte Order Mark (`ï»¿` / `\xEF\xBB\xBF`) to the stream header. This forces Microsoft Excel on Windows and macOS to automatically detect UTF-8 encoding and correctly render non-ASCII accented characters (e.g. José, München, Åbenrå).
- **Filter Preservation:** The export endpoint accepts all search and filter query parameters from the main table, allowing users to export precise slices of data.

---

### 7.3 Artisan n8n Webhook Simulator (`SimulateN8nCommand.php`)

To facilitate end-to-end integration testing without requiring an active n8n instance or live webhook triggers, the backend includes an automated CLI simulator.

The command parses sample CSV files, transforms rows into n8n-format JSON payloads, applies personal email filters (dropping `@gmail.com`, `@yahoo.com`, etc.), and dispatches live HTTP POST requests with Bearer authentication against the webhook endpoint:

```bash
# 1. Preview generated JSON payloads without transmitting (Dry Run Mode):
$ php artisan n8n:simulate --dry-run

# 2. Transmit all sample leads individually with progress tracking:
$ php artisan n8n:simulate

# 3. Transmit entire dataset in a single bulk batch request:
$ php artisan n8n:simulate --bulk

# 4. Simulate real-world scraping cadence with a 500ms delay between POSTs:
$ php artisan n8n:simulate --delay=500

# 5. Execute simulation using a custom CSV file:
$ php artisan n8n:simulate --csv=storage/app/prospects.csv

# 6. Target a remote staging or production instance:
$ php artisan n8n:simulate --base-url=https://api.yourdomain.com
```

---

### 7.4 Built-In Web Dashboard & API Key Management

The backend includes a lightweight, server-rendered Blade dashboard accessible at `/dashboard`. This interface allows administrators to view metrics, filter leads, download CSV exports, and generate/revoke API keys without running the React frontend.

![Figure 7.1: Internal Blade Management Dashboard](LeadsBackend%20Dashboard.PNG)
*Figure 7.1: Internal Blade Management Dashboard showing metrics, filter controls, and leads table.*

![Figure 7.2: API Key Management Table](LeadsAPIKeys.PNG)
*Figure 7.2: API Key Management Table with active status toggles and rate limits.*

![Figure 7.3: One-time plain-text API key display modal](LeadsGenerateKey.PNG)
*Figure 7.3: One-time plain-text API key display modal upon generation.*

---

## 8. Testing Manual: Unit, Feature, Postman & cURL

### 8.1 PHPUnit Automated Test Suite

LeadsBoard includes a comprehensive automated test suite covering all authentication layers, middleware rules, ingestion services, filtering scopes, and CLI commands. Tests run against an isolated in-memory SQLite database (`:memory:`) specified in `phpunit.xml`.

| TEST SUITE | CLASS FILE | TYPE | VALIDATION SCOPE |
| :--- | :--- | :--- | :--- |
| **Auth** | `tests/Feature/AuthTest.php` | Feature | Login, token generation, user profile retrieval (`/me`), invalid credentials, logout revocation. |
| **Webhook** | `tests/Feature/WebhookIngestionTest.php` | Feature | Single & bulk webhook ingestion, deduplication rejection (409), validation rules (422), secret token enforcement (401). |
| **Leads API** | `tests/Feature/LeadsApiTest.php` | Feature | CRUD operations, multi-token search, complex filter combinations, relational sorting, pagination metadata, streamed CSV export. |
| **Stats API** | `tests/Feature/DashboardStatsTest.php` | Feature | Metrics aggregation: summary counts, industry grouping, country breakdown, timeline ranges. |
| **API Keys** | `tests/Feature/ApiKeyAuthTest.php` | Feature | Key creation, SHA-256 hash lookup, per-key rate limit enforcement (429), expired key rejection, deactivated key rejection. |
| **Web UI** | `tests/Feature/DashboardWebTest.php` | Feature | Session authentication, login redirects, Blade dashboard rendering, CSV export from web view. |
| **Simulator** | `tests/Feature/SimulateN8nCommandTest.php` | Feature | CLI command execution, dry-run output formatting, bulk flag handling, CSV parsing. |
| **Ingestion Unit** | `tests/Unit/LeadIngestionServiceTest.php` | Unit | Field mapping logic, name formatting, domain cleanup, title tier heuristics, atomic 3NF persistence. |
| **Models Unit** | `tests/Unit/ModelsTest.php` | Unit | Eloquent relationships (Lead → Company → Industry/Location/Country), scopes, virtual accessors. |

```bash
# Execute all automated tests:
$ php artisan test

# Execute with verbose output and stack traces:
$ php artisan test -v

# Execute a specific test file:
$ php artisan test --filter=WebhookIngestionTest
```

---

### 8.2 Terminal cURL Testing Recipes

```bash
# ─── 1. Authenticate & Obtain Sanctum Token ────────────────────────────────────
curl -X POST http://127.0.0.1:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email": "admin@leadsboard.local", "password": "password"}'

# ─── 2. Fetch Filtered Leads with Search & Seniority Tier ─────────────────────
curl -X GET "http://127.0.0.1:8000/api/v1/leads?title_tier=C-Level&country=Portugal&per_page=5" \
  -H "Authorization: Bearer YOUR_SANCTUM_TOKEN" \
  -H "Accept: application/json"

# ─── 3. Ingest Single Lead via Webhook (n8n Simulation) ────────────────────────
curl -X POST http://127.0.0.1:8000/api/v1/webhook/leads \
  -H "Authorization: Bearer local-dev-webhook-secret-change-me" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
  "Full Name": "Helena Silva",
  "Job Title": "Chief Technology Officer",
  "Corporate Work Email": "helena.silva@techportugal.io",
  "Company Name": "Tech Portugal",
  "Clean Root Domain": "techportugal.io",
  "Industry Classification": "Software",
  "HQ Location": "Lisbon, Lisbon, Portugal"
}'

# ─── 4. Query External Leads Endpoint using API Key ───────────────────────────
curl -X GET http://127.0.0.1:8000/api/v1/external/leads \
  -H "X-API-Key: YOUR_GENERATED_API_KEY" \
  -H "Accept: application/json"

# ─── 5. Download Filtered CSV Export Stream ───────────────────────────────────
curl -X GET "http://127.0.0.1:8000/api/v1/leads/export/csv?industry=Software" \
  -H "Authorization: Bearer YOUR_SANCTUM_TOKEN" \
  --output leads_export.csv
```

---

### 8.3 Postman Integration & Testing Manual

To test the LeadsBoard API using Postman, configure an environment with the following dynamic variables:

1. **Create Postman Environment:**
   - `base_url`: `http://127.0.0.1:8000/api/v1`
   - `sanctum_token`: *(Populated dynamically from login response)*
   - `webhook_secret`: `local-dev-webhook-secret-change-me`
   - `api_key`: *(Generated from dashboard)*

2. **Automate Token Storage via Login Script:**  
   In the `POST /auth/login` request in Postman, paste the following into the **Scripts / Post-response** tab:

   ```javascript
   if (pm.response.code === 200) {
       const json = pm.response.json();
       pm.environment.set("sanctum_token", json.data.token);
       console.log("Sanctum token stored successfully.");
   }
   ```

3. **Headers: Ensure all requests include:**
   - `Accept: application/json` *(Critical: forces Laravel to return JSON error responses instead of HTML redirects).*
   - `Content-Type: application/json` *(for POST / PUT requests).*

---

### 8.4 End-to-End Integration Testing Checklist

| # | TEST SCENARIO | REQUEST SPECIFICATION | EXPECTED HTTP STATUS & RESPONSE |
| :-: | :--- | :--- | :--- |
| **1** | Unauthorized Webhook | `POST /api/v1/webhook/leads` without auth header | `401 Unauthorized` (`"Invalid or missing webhook token."`) |
| **2** | Valid Webhook Ingestion | `POST /api/v1/webhook/leads` with Bearer `WEBHOOK_SECRET` | `201 Created` (Returns normalized lead object with ID) |
| **3** | Duplicate Rejection | `POST /api/v1/webhook/leads` with identical email | `409 Conflict` (`"Duplicate lead — this email already exists."`) |
| **4** | Missing Required Field | `POST /api/v1/webhook/leads` without company name | `422 Unprocessable` (`"Company name is required."`) |
| **5** | Bulk Batch Ingestion | `POST /api/v1/webhook/leads/bulk` with 10 leads | `201 Created` / `207 Multi-Status` with summary object |
| **6** | Valid User Login | `POST /api/v1/auth/login` with admin credentials | `200 OK` with plain-text Sanctum token |
| **7** | Invalid Login Attempt | `POST /api/v1/auth/login` with incorrect password | `401 Unauthorized` (`"Invalid credentials."`) |
| **8** | Token Logout Revocation | `POST /api/v1/auth/logout` with Sanctum Bearer token | `200 OK` (Token record deleted from database) |
| **9** | Unauthenticated Access | `GET /api/v1/leads` without Bearer token | `401 Unauthorized` (`"Unauthenticated."`) |
| **10** | Multi-Token Search | `GET /api/v1/leads?search=John,Portugal` | `200 OK` (Only prospects matching BOTH criteria) |
| **11** | Valid API Key Query | `GET /api/v1/external/leads` with `X-API-Key` | `200 OK` (Paginated leads) |
| **12** | API Key Rate Exceeded | `GET /api/v1/external/leads` exceeding rate limit | `429 Too Many Requests` with `Retry-After` header |
| **13** | Deactivated API Key | `GET /api/v1/external/leads` with inactive key | `401 Unauthorized` (`"Invalid, expired, or deactivated API key."`) |
| **14** | Streamed CSV Export | `GET /api/v1/leads/export/csv` | `200 OK` (`Content-Type: text/csv`, UTF-8 BOM present) |

---

## 9. Error Catalog & Troubleshooting Guide

### 9.1 HTTP Status Code Reference Catalog

| STATUS CODE | INTERNAL TRIGGER CONDITION | ROOT CAUSE & SYSTEM BEHAVIOR | RECOMMENDED REMEDIATION |
| :--- | :--- | :--- | :--- |
| **`201 Created`** | Successful resource creation | Lead, user token, or API key successfully created and persisted. | Normal operational state. Inspect `data` object for returned record ID. |
| **`207 Multi-Status`** | Partial bulk batch execution | Some items in the bulk array succeeded while others failed validation or were duplicates. | Inspect the `results` array to identify failed indices and individual error reasons. |
| **`401 Unauthorized`** | Authentication failure | • Webhook secret missing/mismatched.<br>• Sanctum token missing, invalid, or expired.<br>• API key missing, inactive, or past expiration date.<br>• Invalid login credentials submitted. | • Verify `WEBHOOK_SECRET` in `.env` and header format `Bearer <token>`.<br>• Re-authenticate via `/auth/login` to obtain fresh Sanctum token.<br>• Check API key active toggle in `/dashboard/api-keys`. |
| **`404 Not Found`** | Model binding or route missing | Target lead ID does not exist in `leads` table (e.g. `GET /leads/99999`). | Verify the resource ID. The record may have been previously deleted. |
| **`409 Conflict`** | Duplicate lead detection | The submitted `corporate_email` already exists in the database. | Expected deduplication behavior. If updating an existing lead, use `PUT /leads/` instead. |
| **`422 Unprocessable`** | Input validation failure | Payload failed `FormRequest` rules (e.g. missing required field, non-email string). | Inspect the `errors` response object for specific field validation failures. |
| **`429 Too Many Requests`** | Rate limit exceeded | API Key has exceeded its assigned requests-per-minute threshold. | Client must back off for the duration specified in the `Retry-After` response header. |
| **`500 Server Error`** | Unhandled PHP Exception | Uncaught exception, missing database file, uncompiled classes, or missing `APP_KEY`. | Inspect `storage/logs/laravel.log` or run `php artisan pail` for full stack trace. |
| **`503 Unavailable`** | Missing server configuration | `WEBHOOK_SECRET` environment variable is missing or blank on the server. | Set `WEBHOOK_SECRET` in `.env` and restart the application. |

---

### 9.2 Common Platform Quirks & Remediation

#### 🐢 Windows Defender 500ms Latency Overhead
- **Symptom:** Local API requests on Windows take exactly ~500ms even for trivial database queries.
- **Cause:** On every incoming request, Laravel dynamically loads 500+ framework files. Windows Defender Real-Time Protection intercepts and scans each file read operation on disk.
- **Solution:** Add the project folder to the Windows Defender Exclusions list (*Settings → Virus & Threat Protection → Exclusions → Add Folder*). Response times will immediately drop from 500ms to 15-30ms. In production Linux environments, this overhead does not exist.

#### 🔒 SQLite Database Lock & Read-Only Errors
- **Symptom:** `SQLSTATE[HY000]: General error: 8 attempt to write a readonly database`.
- **Cause:** The web server process (e.g. `www-data` or Apache) lacks write permissions to the SQLite file or parent directory.
- **Solution:** Ensure both the file and `database/` directory have write permissions:  
  `chmod 664 database/database.sqlite && chmod 775 database/`

#### 🔄 Stale Configuration Cache
- **Symptom:** Changes to `.env` (such as updating `WEBHOOK_SECRET` or `CORS_ALLOWED_ORIGINS`) are ignored by the server.
- **Cause:** Laravel cached configuration files via `php artisan config:cache`, which bypasses `.env` file reading.
- **Solution:** Clear all caches:  
  `php artisan optimize:clear`

---

## 10. Security, Data Integrity & Hardening

### 10.1 Cryptographic Standards & Timing-Attack Defense

- **Timing-Safe String Comparison:** Webhook secret tokens are validated using `hash_equals()`. Standard equality operators (`===`) return early on the first mismatched byte, allowing attackers to measure execution time variations and deduce secrets byte-by-byte. `hash_equals()` executes in constant time regardless of match position.
- **One-Way API Key Hashing:** External API keys are stored exclusively as 64-character SHA-256 hex hashes. Even in the event of a full database leak, attackers cannot derive the plain-text integration keys.
- **Password Hashing:** User passwords are encrypted using bcrypt with an adaptive cost factor of 12 rounds (`BCRYPT_ROUNDS=12`).

---

### 10.2 Relational Integrity & Transactional Guarantees

- **Database-Level Unique Constraints:** Enforced on `leads.corporate_email`, `companies.clean_root_domain`, `industries.name`, `locations.raw_location`, `countries.name`, `users.email`, and `api_keys.key`.
- **Foreign Key Protection:** Child records use `nullOnDelete()` constraints. Deleting a company or industry safely sets foreign keys to `NULL` rather than cascading destruction across dependent prospect data.
- **ACID Transaction Isolation:** Multi-entity ingestion operations execute within `DB::transaction()`. A failure in resolving a location or company immediately aborts and rolls back all database operations, preventing orphaned partial records.

---

### 10.3 Production Hardening Checklist

| AREA | ITEM | REQUIREMENT | STATUS |
| :--- | :--- | :--- | :--- |
| **Environment** | `APP_ENV` | Must be set to `production`. | Mandatory |
| **Debug Mode** | `APP_DEBUG` | Must be set to `false`. Stack traces expose environment secrets. | Critical |
| **App Key** | `APP_KEY` | Must be freshly generated per environment (`php artisan key:generate`). | Critical |
| **Webhook Secret** | `WEBHOOK_SECRET` | Must be a 64-character random hex string. Never use dev defaults. | Critical |
| **Admin Password** | Default Account | Change default password (`password`) immediately upon first boot. | Critical |
| **CORS** | `CORS_ALLOWED_ORIGINS` | Lock down strictly to authorized frontend domains. | Mandatory |
| **SSL / TLS** | HTTPS Protocol | Enforce HTTPS and set `APP_URL=https://api.yourdomain.com`. | Mandatory |
| **File Permissions** | Storage & Bootstrap | `storage/` and `bootstrap/cache/` set to `775` permissions. | Mandatory |
| **Performance** | Cache Optimization | Run `config:cache`, `route:cache`, `view:cache` in production. | Recommended |

---

## 11. Universal Deployment Guide

### 11.1 Docker & Render.com Cloud Native Deployment

LeadsBoard includes a complete production `Dockerfile` and a `render.yaml` Infrastructure-as-Code blueprint for automated containerized deployment.

#### DOCKER BUILD SPECIFICATION (`Dockerfile`):
- **Base Image:** `php:8.2-apache`
- Installs system libraries and PHP extensions: `pdo_mysql`, `pdo_pgsql`, `pdo_sqlite`, `bcmath`, `mbstring`, `zip`, `opcache`.
- Enables Apache rewrite and headers modules; mounts custom VirtualHost (`docker/apache.conf`).
- Runs Composer with `--no-dev --optimize-autoloader --classmap-authoritative` for maximum opcode performance.

#### CONTAINER BOOT ENGINE (`docker/entrypoint.sh`):
On container startup, the entrypoint script automatically:
1. Configures Apache to bind to Render's dynamic `$PORT` variable (defaulting to `80`/`10000`).
2. Sets ownership on `storage/` and `bootstrap/cache/` to `www-data`.
3. Executes `php artisan migrate --force` (if `RUN_MIGRATIONS=true`).
4. Seeds default administrator account (if `SEED_ADMIN=true`).
5. Warms production caches: `config:cache`, `route:cache`, `view:cache`.
6. Launches Apache in foreground mode.

---

### 11.2 Shared Hosting Deployment (Hostinger / cPanel)

1. **Upload Application Files:** Upload the repository files to your hosting directory (e.g. `/home/u123456789/leadsboard`).
2. **Document Root Configuration:** In hPanel / cPanel Domain Settings, point the website Document Root to the application's `public/` subfolder (e.g. `/home/u123456789/leadsboard/public`). Never expose the project root folder.
3. **Create MySQL Database:** Create a MySQL database and user in hPanel.
4. **Configure `.env`:**

```env
APP_NAME=LeadsBoard
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=u123456789_leadsboard
DB_USERNAME=u123456789_leadsuser
DB_PASSWORD=YourStrongDatabasePassword123!

WEBHOOK_SECRET=your_64_character_cryptographic_random_hex_secret
CORS_ALLOWED_ORIGINS=https://leads.yourdomain.com
```

5. **Initialize Application via SSH:**

```bash
$ php artisan key:generate --force
$ php artisan migrate --seed --force
$ php artisan config:cache
$ php artisan route:cache
$ php artisan view:cache
```

---

## 12. Scalability Roadmap & Future Recommendations

As the B2B lead pipeline scales from thousands to millions of prospects, the following architectural upgrades are recommended:

| SCALING AREA | CURRENT IMPLEMENTATION | RECOMMENDED UPGRADE | IMPLEMENTATION STRATEGY |
| :--- | :--- | :--- | :--- |
| **Asynchronous Ingestion** | Synchronous HTTP execution in controller. | Queue-backed ingestion (`jobs` table or Redis). | Webhook controllers immediately return `HTTP 202 Accepted` with a job UUID. Ingestion and enrichment execute asynchronously via background queue workers (`php artisan queue:work`). |
| **Caching & Rate Limiting** | Database cache driver (`CACHE_STORE=database`). | Redis in-memory data store. | Set `CACHE_STORE=redis` and `QUEUE_CONNECTION=redis`. Sub-millisecond rate limit checks and API key cache lookups. |
| **Full-Text Search** | SQL `LIKE %term%` wildcards across joined tables. | Search engine integration (Meilisearch or Algolia via Laravel Scout). | Install `laravel/scout` with Meilisearch. Enables typo-tolerant, instant full-text indexing across 10M+ prospects with sub-10ms response times. |
| **Pagination Strategy** | Offset-based pagination (`paginate()`). | Cursor-based pagination (`cursorPaginate()`). | Eliminates high-offset database scanning latency on deep pages (e.g. Page 1,000+). |
| **Granular Permissions** | Single admin role on `users` table. | Role-Based Access Control (RBAC). | Implement `spatie/laravel-permission` to define granular roles: `SuperAdmin`, `SalesManager`, `SDR`, `API-Read-Only`. |
| **Audit Logging** | Timestamps only. | Full change audit trails. | Integrate `spatie/laravel-activitylog` to record which user or API key modified/deleted leads and when. |
| **Outbound Event Webhooks** | Manual `N8nService` wrapper. | Model event webhook dispatchers. | Automatically fire outbound HTTP webhooks to n8n or Slack when a lead transitions to `qualified` status. |

---

### Colophon & Document Version

**LeadsBoard Backend API Documentation & Instructional Manual**  
*Release Version 1.0.0 • Laravel 12 / PHP 8.2+ Architecture • Generated August 2026*  
*Designed for enterprise operations, stability, and high-throughput automation.*
