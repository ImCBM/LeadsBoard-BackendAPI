# Architecture & System Overview

> **Audience**: Developers, n8n operators, and non-tech project stakeholders.
>
> **Last Updated**: September 2026

---

## What Is This?

The **LeadsBoard Backend API** is a Laravel 11 (PHP 8.2) REST API that powers the B2B Lead Pipeline Dashboard. It:

- **Receives leads** from n8n automation workflows and external integrations
- **Stores them** in a normalized relational database (3NF)
- **Serves them** to the React frontend dashboard via JSON API
- **Manages tags, filtering, bulk operations**, and CSV exports

Think of it as the "brain" — n8n scrapes leads from LinkedIn and sends them here, then the dashboard reads from here to display everything.

---

## High-Level Data Flow

```
┌─────────────────┐     Webhook POST      ┌──────────────────────┐
│   n8n Workflow   │ ──────────────────▶   │                      │
│  (LinkedIn       │   Bearer token auth   │   LeadsBoard API     │
│   Scraper)       │                       │   (Laravel)          │
└─────────────────┘                        │                      │
                                           │  ┌────────────────┐  │
┌─────────────────┐     Sanctum Token      │  │ Ingestion      │  │
│  React Frontend  │ ◀────────────────▶    │  │ Service        │  │
│  (Dashboard)     │   REST API calls      │  │                │  │
└─────────────────┘                        │  │ ┌──────────┐   │  │
                                           │  │ │ Database │   │  │
┌─────────────────┐     API Key auth       │  │ │ (SQLite/ │   │  │
│  External Tools  │ ──────────────────▶   │  │ │  PgSQL/  │   │  │
│  (3rd party)     │   read-only access    │  │ │  MySQL)  │   │  │
└─────────────────┘                        │  │ └──────────┘   │  │
                                           │  └────────────────┘  │
                                           └──────────────────────┘
```

---

## Deployment Environments

The system runs on **three** environments. Each has its own database and URL.

| Environment | Purpose | Backend Host | Frontend Host | Database | Branch |
|---|---|---|---|---|---|
| **Local Dev** | Development & testing | `http://127.0.0.1:8000` | `http://localhost:5173` | SQLite (file) | Any |
| **Pre-Production** | Staging / QA | `https://leadsboard-backend.onrender.com` | `https://leadsboard-frontend.vercel.app/` | PostgreSQL (Render) | `main` |
| **Production** | Live system | `https://certicode.net/backend-b2bleadscraper` | `https://b2bleadscraper.certicode.net/` | SQLite or MySQL (Hostinger) | Manual deploy |

### Pre-Production (Render + Vercel)

- **Backend**: Hosted on [Render](https://render.com) as a Docker web service (free tier)
- **Frontend**: Hosted on [Vercel](https://vercel.com)
- **Database**: PostgreSQL managed by Render
- **Auto-deploys**: On push to `main` branch
- **Config file**: `.env.render`
- Render uses `render.yaml` for infrastructure-as-code configuration
- Migrations run automatically on deploy (via `RUN_MIGRATIONS=true`)

### Production (Hostinger)

- **Backend**: Hosted on [Hostinger](https://hostinger.com) shared hosting
- **Frontend**: Also on Hostinger (or pointed via CORS)
- **Database**: SQLite (default) or MySQL (available, commented out in config)
- **Deploys**: Manual upload / Git push
- **Config file**: `.env.hostinger`
- URL is served under a subdirectory: `/backend-b2bleadscraper`

### Local Development

- Run with `php artisan serve` (port 8000 by default)
- Uses SQLite file at `database/database.sqlite`
- Config file: `.env` (copied from `.env.example`)

---

## Environment Variables Reference

Below is every environment variable the system uses, what it controls, and what values to set.

### Core Laravel Settings

| Variable | Description | Example Values |
|---|---|---|
| `APP_NAME` | Application name, shown in logs and emails | `LeadsBoard` |
| `APP_ENV` | Environment mode | `local`, `staging`, `production` |
| `APP_KEY` | Encryption key (auto-generated) | `base64:vremMFji...` |
| `APP_DEBUG` | Show detailed errors? **Must be `false` in production** | `true` / `false` |
| `APP_URL` | Base URL of the backend | `https://certicode.net/backend-b2bleadscraper` |
| `APP_PORT` | Local dev server port | `8000` |

### Database

| Variable | Description | Example Values |
|---|---|---|
| `DB_CONNECTION` | Database driver | `sqlite`, `pgsql`, `mysql` |
| `DB_HOST` | Database server address (not used for SQLite) | `127.0.0.1`, `localhost` |
| `DB_PORT` | Database port | `3306` (MySQL), `5432` (PostgreSQL) |
| `DB_DATABASE` | Database name (or file path for SQLite) | `leadsboard`, `:memory:` |
| `DB_USERNAME` | Database user | `root`, `leadsboard_user` |
| `DB_PASSWORD` | Database password | *(secret)* |
| `DATABASE_URL` | Full connection string (used by Render) | `postgres://user:pass@host/db` |

> **Note for Render**: Render provides `DATABASE_URL` automatically. Laravel's `config/database.php` parses this. You don't need to set the individual `DB_*` vars separately.

### Authentication & Security

| Variable | Description | Example Values |
|---|---|---|
| `WEBHOOK_SECRET` | Static token for n8n webhook authentication. Shared across staging & production. | `b2bleadscraper-prod-webhook-token-7f9a2e8c4d1b` |
| `BCRYPT_ROUNDS` | Password hashing cost factor | `12` (prod), `4` (testing) |

### API & Rate Limiting

| Variable | Description | Default |
|---|---|---|
| `API_DEFAULT_RATE_LIMIT` | Default requests/minute for API key users | `120` |
| `LEADS_PER_PAGE` | Default pagination size for lead listings | `25` |

### CORS & Frontend

| Variable | Description | Example |
|---|---|---|
| `CORS_ALLOWED_ORIGINS` | Comma-separated list of allowed frontend origins | `https://*.vercel.app,http://localhost:5173,https://certicode.net` |

### Deployment Automation

| Variable | Description | Default |
|---|---|---|
| `RUN_MIGRATIONS` | Auto-run `php artisan migrate --force` on container start | `true` |
| `SEED_ADMIN` | Auto-create admin user on container start | `true` |
| `N8N_SIMULATE_BASE_URL` | Base URL for the `n8n:simulate` artisan command | Auto-detected locally |

### Logging

| Variable | Description | Notes |
|---|---|---|
| `LOG_CHANNEL` | Where to send logs | `stack` (local), `stderr` (Docker/Render) |
| `LOG_LEVEL` | Minimum severity to log | `debug` (local), `info` (staging), `error` (production) |

---

## Project Structure

```
LeadsBoard-BackendAPI/
├── app/
│   ├── Console/Commands/       # Artisan CLI commands (n8n simulator, cleanup, retag)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/            # JSON API controllers (AuthController, LeadController, etc.)
│   │   │   ├── ApiKeyController.php    # Web dashboard API key management
│   │   │   └── DashboardController.php # Blade dashboard (server-side rendered)
│   │   ├── Middleware/         # ValidateWebhookToken, ValidateApiKey
│   │   └── Requests/          # Form request validation classes
│   ├── Models/                 # Eloquent models (Lead, Company, Tag, etc.)
│   ├── Providers/              # Service providers
│   └── Services/               # Business logic (LeadIngestionService, etc.)
├── config/                     # Laravel configuration files
├── database/
│   ├── migrations/             # Schema migration files (chronological)
│   ├── seeders/                # AdminUser, Tags, sample Leads
│   └── database.sqlite         # Local SQLite database file
├── docker/                     # Apache config, entrypoint script
├── docs/                       # This documentation
├── routes/
│   ├── api.php                 # All API routes (/api/v1/...)
│   └── web.php                 # Blade dashboard routes
├── tests/
│   ├── Feature/                # HTTP-level integration tests
│   └── Unit/                   # Service and model unit tests
├── .env.example                # Template for local development
├── .env.render                 # Pre-production (Render) config
├── .env.hostinger              # Production (Hostinger) config
├── Dockerfile                  # Docker image for Render deployment
├── render.yaml                 # Render infrastructure-as-code
└── phpunit.xml                 # Test runner configuration
```

---

## Authentication Modes

The system has **three separate authentication mechanisms**, each protecting a different set of routes:

| Auth Mode | Who Uses It | Protected Routes | How It Works |
|---|---|---|---|
| **Webhook Token** | n8n automation, external scripts | `/api/v1/webhook/*` | Static secret in `WEBHOOK_SECRET` env var, sent as `Authorization: Bearer <token>` or `X-Webhook-Token` header |
| **Sanctum Token** | React frontend dashboard | `/api/v1/leads/*`, `/api/v1/tags/*`, `/api/v1/stats/*` | Login with email/password → receive bearer token → use on all subsequent requests |
| **API Key** | External third-party consumers | `/api/v1/external/*` | Generated via the dashboard UI, sent as `Authorization: Bearer <key>`, `X-API-Key` header, or `?api_key=` query param |

See the [API Reference](./02-API-REFERENCE.md) for full details on each auth mode.

---

## Artisan Commands

Custom artisan commands available for ops and development:

| Command | Description |
|---|---|
| `php artisan n8n:simulate` | Simulates n8n sending leads to the webhook endpoints. Useful for testing without a real n8n instance. |
| `php artisan leads:cleanup-test` | Removes leads tagged with system tags (Test, Demo, Sample). For cleaning staging data. |
| `php artisan leads:retag` | Retroactively applies tag rules to existing leads. |

---

## Key Design Decisions

1. **3NF Database**: Leads, Companies, Locations, Countries, and Industries are separate tables. This avoids duplicating company info across hundreds of leads from the same company.

2. **Dual Field Name Support**: The ingestion service accepts both n8n-style field names (`"Full Name"`, `"Corporate Work Email"`) and snake_case (`"full_name"`, `"corporate_email"`). This means n8n operators don't need to transform field names before sending.

3. **Deduplication by Email**: A lead's `corporate_email` is the unique identifier. If n8n sends a lead with an email that already exists, the API returns a `409 Conflict` instead of creating a duplicate.

4. **Company Deduplication**: Companies are deduplicated first by `clean_root_domain`, then by `name`. If a new lead comes in for an existing company, the company record is enriched with any new data (e.g., adding a LinkedIn page or industry).

5. **Tags are Freeform + Auto-Created**: When a lead is ingested with tags (e.g., `"Tags": "VIP, Q3 Campaign"`), any tag that doesn't already exist is automatically created. Tags have a `slug` for matching, so "High Priority" and "high-priority" resolve to the same tag.
