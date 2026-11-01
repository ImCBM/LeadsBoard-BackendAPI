# Architecture & System Overview

> **Audience**: Developers, n8n automation engineers, and project stakeholders.  
> **Status**: Active / Production Architecture  
> **Technology Stack**: Laravel 12 (PHP 8.2+), React 18 (Vite), SQLite / PostgreSQL / MySQL

---

## What Is This?

The **LeadsBoard System** is a dual-plane B2B Lead Pipeline platform comprising:

1. **Laravel Backend (Developers & Business Logic Hub)**:  
   The core engine and internal developer workbench. It handles data ingestion, 3NF normalization, deduplication, dual-bucket rate limiting, dynamic field-level masking, audit tracing, and subscription lifecycle management. It also provides the **Internal Developer GUI** (`/dashboard/api-keys`) for managing developer-level keys, system pipeline tokens (e.g., n8n ingestion), and system health.
2. **React Frontend (Customer Portal)**:  
   The customer-facing application where external SaaS subscribers log in, view enriched leads, and self-serve their own API keys strictly within their active subscription tier (`free`, `pro`, `enterprise`).

---

## High-Level Data Flow

```text
┌─────────────────┐       Webhook POST (lb_sec_...)       ┌────────────────────────┐
│  n8n Automation │ ──────────────────────────────────▶   │                        │
│ (LinkedIn ETL / │     X-API-Key or Bearer token         │   LeadsBoard Backend   │
│  Lead Scrapers) │     (scope: webhooks:post)            │   (Laravel 12 Hub)     │
└─────────────────┘                                       │                        │
                                                          │  ┌──────────────────┐  │
┌─────────────────┐       Sanctum / Customer API Key      │  │ Ingestion &      │  │
│ React Frontend  │ ◀─────────────────────────────────▶   │  │ Validation Svc   │  │
│(Customer Portal)│       Rate-limited & Field-masked     │  │                  │  │
└─────────────────┘                                       │  │ ┌──────────────┐ │  │
                                                          │  │ │ Dual-Bucket  │ │  │
┌─────────────────┐       Developer / System Keys         │  │ │ Rate Limiter │ │  │
│ Internal Tools  │ ──────────────────────────────────▶   │  │ └──────────────┘ │  │
│ (CLI / CI-CD)   │     Wildcard (*) & Developer Tier     │  │ ┌──────────────┐ │  │
└─────────────────┘                                       │  │ │ 3NF Database │ │  │
                                                          │  │ └──────────────┘ │  │
                                                          │  └──────────────────┘  │
                                                          └────────────────────────┘
```

---

## Deployment Environments

The system operates across **three** environments:

| Environment | Purpose | Backend Host | Frontend Host | Database | Branch |
|---|---|---|---|---|---|
| **Local Dev** | Development & testing | `http://127.0.0.1:8000` | `http://localhost:5173` | SQLite (`database.sqlite`) | Feature branches |
| **Pre-Production** | Staging / QA | `https://leadsboard-backend.onrender.com` | `https://leadsboard-frontend.vercel.app/` | PostgreSQL (Render) | `main` |
| **Production** | Live system | `https://certicode.net/backend-b2bleadscraper` | `https://b2bleadscraper.certicode.net/` | SQLite / MySQL (Hostinger) | Release tags |

---

## Authentication Modes

The system unifies all API authentication into a centralized, normalized architecture:

| Auth Mode | Target Audience | Protected Routes | Mechanism |
|---|---|---|---|
| **Secret API Key (`lb_sec_...`)** | n8n automation pipelines, ETL scrapers, internal microservices | `/api/v1/webhook/*`, `/api/v1/leads/*` | SHA-256 hashed token passed via `X-API-Key` or `Authorization: Bearer`. Must carry `webhooks:post` or resource mutation scopes. |
| **Public API Key (`lb_pub_...`)** | Client-side apps, read-only widgets | `/api/v1/leads` (read), `/api/v1/stats` | Restricted to read-only scopes (`leads:read`, `tags:read`, `stats:read`). Mutating operations are rejected (`403 Forbidden`). |
| **Sanctum Bearer Token** | React customer dashboard sessions | Customer web routes (`/api/developer/*`) | Issued via `/api/v1/auth/login`. Subject to user role and subscription tier. |
| **Developer GUI Session** | Internal developers & administrators | `/dashboard/api-keys`, `/dashboard` | Standard web session with CSRF protection, granting access to developer key generation and audit logs. |

> [!NOTE]
> **Static Webhook Secret Removed**: The legacy `.env` `WEBHOOK_SECRET` has been completely eliminated. All webhook endpoints now authenticate via standard secret API keys possessing the `webhooks:post` scope. A deterministic test key `lb_sec_n8n_test_automation_key` is seeded for pipeline testing.

---

## Normalized 3NF Tier Architecture

Access is governed by four normalized database tiers:

| Tier Slug | Tier Name | Target Audience | Rate Limit | Daily Limit | Max Keys | Field Masking | Allowed Scopes |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `free` | **Free Client** | External free users | 60 req/min | 5,000 req | 3 | `contact_number: null` | Read-only (`leads:read`, `tags:read`, `stats:read`) |
| `pro` | **Pro / Growth** | External paying users | 300 req/min | 50,000 req | 10 | Unmasked | Read, write, delete, export |
| `enterprise` | **Enterprise** | External high-volume clients | 1,200 req/min | 500,000 req | 50 | Unmasked | All client scopes + bulk operations |
| `developer` | **Developer / Internal**| Internal devs, n8n pipelines | 2,400 req/min | 1,000,000 req | 100 | Unmasked | Full wildcard (`*`) & `webhooks:post` |

---

## Rate Limiting & Protection

Requests are protected by a **Dual-Bucket Rate Limiter** (`TierLimiter.php`):

1. **Account-Level Aggregate Bucket**: Caps total requests across *all* active keys owned by an account within a 1-minute window.
2. **Key-Level Budget Bucket**: Enforces custom limits assigned to individual keys (capped at the account's tier limit).
3. **Daily Quota**: Enforces a 24-hour total request ceiling. Exceeded limits return `429 Too Many Requests` with `Retry-After` and `X-RateLimit-*` headers.

---

## Artisan Commands

Custom CLI commands available for operators and developers:

| Command | Description |
|---|---|
| `php artisan n8n:simulate` | Simulates n8n webhook ingestion from CSV. Supports `--api-key` (defaults to deterministic key) and `--limit`. |
| `php artisan leads:cleanup-test` | Granular cleanup by channel, company pattern, or tags. |
| `php artisan leads:tag` | Retroactively tags leads based on domain or rules. |

---

## Key Design Principles

1. **Single Source of Truth**: Laravel models, database tables, and service layers are authoritative.
2. **3NF Normalized Database**: Separate tables for `leads`, `companies`, `industries`, `locations`, `countries`, `tags`, `tiers`, `api_scopes`, `subscriptions`, and `api_traces`.
3. **Field-Level Authorization**: Dynamic masking via `LeadResource.php` ensures free tier callers never receive restricted fields like direct phone numbers.
4. **Deduplication by Email & Contact Number**: Work email and phone number are unique identifiers to prevent duplicate ingestion.
5. **Traceability**: Every API request is logged with a UUID `trace_id`, execution duration, status code, and IP in `api_traces`.
