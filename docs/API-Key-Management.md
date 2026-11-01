# API Key Management Architecture

Architecture Decisions, Design Principles, and Implementation Guidelines

- **Document Type**: Technical Architecture Reference & Implementation Standard
- **Status**: Implemented & Active Architecture
- **Technology Stack**: Laravel 12 (Backend/API & Developer Workbench), React 18 (Customer Portal), SQLite / MySQL
- **Purpose**: Establish an authoritative, secure, and normalized API key management architecture aligned with Laravel as the Developers' and Business Logic Hub and React as the Customer Portal.

---

## 1. Overview

The LeadsBoard application consists of two distinct operational systems sharing a unified database and business logic core:

1. **Laravel Backend (Developers & Business Logic Hub)**:  
   The core operational engine and internal developer workbench. It owns all central business logic, database migrations and 3NF normalization, authentication, authorization, dual-bucket rate limiting, audit trace logging, subscription management, and core API services. It provides the **Internal Developer & Administrative GUI** (`/dashboard/api-keys`), where internal developers, automation engineers, testers, and administrators manage developer-level keys, configure pipeline webhooks (e.g., n8n ingestion), monitor real-time audit logs, and oversee accounts.

2. **React Frontend (Customer Portal)**:  
   A dedicated customer-facing single-page application (`/developer`) where external SaaS clients log in, manage their individual or organization accounts, view subscription plans, and self-serve their own API keys strictly within their purchased subscription entitlements.

Both systems communicate with the same Laravel backend services and database.

### Target User Personas & Authority

- **Internal Technical Personnel (Laravel Hub)**:
  - **Developers & Automation Engineers**: Generate developer-level keys for internal services, testing, and automated pipelines (n8n, scraper scripts, Google Sheets ingestion).
  - **Super Admins**: Full administrative control across all customer accounts, permission policies, and system keys.
  - **QA Testers**: Execute regression test suites and sandbox validations.
- **External Customers (React Customer Portal)**:
  - **Free Tier Clients**: Individual users exploring the API with read-only scopes, masked sensitive fields, and tight rate limits.
  - **Pro / Growth Subscribers**: Paying clients with read, write, delete, and CSV export capabilities.
  - **Enterprise Organizations**: High-volume corporate customers with team-level access, bulk operations, and high throughput.

The primary architectural objective is to maintain a centralized API key management architecture that provides full flexibility, unmasked data access, and high throughput for internal developers and automated pipelines while strictly enforcing subscription boundaries for external customers.

---

## 2. Core Architectural Decision

**Two interfaces, one centralized API key management system.**

The application maintains two dedicated interfaces tailored to their respective audiences:

### A. Developer & Administrative Interface (Laravel GUI)

*Primary location: `/dashboard/api-keys` (Blade)*

Used exclusively by internal developers, automation engineers, testers, and administrators.

Responsibilities include:
- **Generating Developer-Level API Keys** for internal development, staging, automated ingestion pipelines (e.g., n8n webhooks, scraper bots, Google Sheets integrations), and automated test suites.
- Managing developer keys with unmasked data access, high rate limits, and full system scopes (`*`, `webhooks:post`, `leads:import`, `leads:export`).
- Administering customer accounts, subscriptions, and permission policies.
- Managing customer keys when administrative assistance is required, ensuring customer keys inherit their respective subscription tier.
- Reviewing real-time audit traces (`api_traces`), query parameters, and latency metrics.
- Revoking and rotating system and customer API keys.

### B. Customer Portal (React Frontend)

*Primary location: `/developer` (React / Vite)*

Used exclusively by external SaaS customers.

Responsibilities include:
- Self-serving and generating customer API keys within their active subscription plan ceiling.
- Viewing and revoking their own active API keys.
- Selecting permissible scopes from their subscription tier (e.g., Free users are locked to read-only; write/export scopes require Pro+).
- Monitoring active key counts against their tier maximum (e.g., 3 for Free, 10 for Pro, 50 for Enterprise).
- Viewing plan rate limits, usage metrics, and field masking indicators.

### Architectural Principle

Different interfaces and authorization contexts are acceptable. Duplicated business logic and independent sources of truth are not. Laravel owns all validation, hashing, entitlement checks, rate limits, and audit logs.

---

## 3. System Architecture

The dual-plane architecture is structured as follows:

```text
                 REACT FRONTEND                       INTERNAL SYSTEMS
                (Customer Portal)              (n8n Pipelines, Scrapers, CI/CD)
                       |                                       |
                       | Customer REST API                     | Webhooks / Developer API
                       v                                       v
+-----------------------------------------------------------------------------------+
|                                 LARAVEL BACKEND                                   |
|                          (Developers & Business Logic Hub)                        |
|                                                                                   |
|  +--------------------------+  +----------------------+  +---------------------+  |
|  | Customer API Controller  |  | Internal Admin GUI   |  | Webhook Ingestion   |  |
|  | (/api/developer/api-keys)|  | (/dashboard/api-keys)|  | (/api/v1/webhooks)  |  |
|  | Client entitlement checks|  | Developer Workbench  |  | n8n pipelines       |  |
|  +--------------------------+  +----------------------+  +---------------------+  |
|                                                                                   |
|  +-----------------------------------------------------------------------------+  |
|  |                           Shared Business Logic                             |  |
|  |                                                                             |  |
|  | - ApiKeyService: Key generation, prefixing, quota & tier ceiling gating    |  |
|  | - TierLimiter: Dual-bucket rate limiting (Account Aggregate + Key Budget)   |  |
|  | - AuthenticateApiRequest: Token extraction, SHA-256 hash lookup, validation |  |
|  | - RequireScope: Granular scope authorization & wildcard (*) expansion       |  |
|  | - LeadResource: Field-level authorization & dynamic masking                 |  |
|  | - ApiTraceLogger: UUID trace generation, payload & latency auditing        |  |
|  +-----------------------------------------------------------------------------+  |
+-----------------------------------------------------------------------------------+
                                           |
                                           v
                             SHARED DATABASE (3NF Normalized)
                                           |
       +---------------+-------------------+-------------------+---------------+
       |               |                   |                   |               |
     users           tiers             api_scopes         api_keys        api_traces
       |               |                   |                   |
 subscriptions    tier_scopes     tier_field_restrictions api_key_scopes
 (User <-> Tier)  (Tier <-> Scope) (Field masking rules)  (Key <-> Scope)
```

The Laravel backend remains the sole authoritative source of truth.

---

## 4. API Key Ownership Models

API keys must have explicit ownership. The system supports three ownership contexts:

### 4.1 Internal Developer & System Ownership
- Internal developers, system daemons, CI/CD runners, and automated ingestion pipelines (n8n) own keys tied to the `developer` tier.
- These keys represent internal infrastructure access, testing credentials, or webhook ingestion credentials.

### 4.2 Individual Customer Ownership
- Regular SaaS customers own API keys through their individual user accounts.
- A customer can create, view, and revoke keys strictly bounded by their active subscription tier.
- Complete account isolation: customer A can never view or manipulate customer B's keys.

### 4.3 Organization Ownership (Enterprise)
- Enterprise customers utilize organization-level ownership.
- Multiple authorized team members can generate and manage keys that belong to the corporate organization account.
- Audit logs track which individual member created, rotated, or revoked an organization key.

---

## 5. Permission, Entitlement, and Tier Architecture

The system enforces a clear separation between **Customer Subscription Tiers** and the **Internal Developer Tier**.

### 5.1 The Four Normalized Tiers

| Tier Slug | Tier Name | Target Audience | Rate Limit | Daily Limit | Max Keys | Can Export | Bulk Ops |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `free` | **Free Client** | External free users | 60 req/min | 5,000 req | 3 | No | No |
| `pro` | **Pro / Growth** | External paying users | 300 req/min | 50,000 req | 10 | Yes | No |
| `enterprise` | **Enterprise** | External high-volume clients | 1,200 req/min | 500,000 req | 50 | Yes | Yes |
| `developer` | **Developer / Internal** | Internal devs, n8n pipelines | 2,400 req/min | 1,000,000 req | 100 | Yes | Yes |

### 5.2 System Entitlement Matrix

| Resource / Action / Field | Free Client (`free`) | Pro / Growth (`pro`) | Enterprise (`enterprise`) | Developer / Internal (`developer`) |
| :--- | :--- | :--- | :--- | :--- |
| **Leads: Read** (`leads:read`) | Yes | Yes | Yes | Yes |
| **Leads: Write** (`leads:write`) | No | Yes | Yes | Yes |
| **Leads: Delete** (`leads:delete`) | No | Yes | Yes | Yes |
| **Leads: Export CSV** (`leads:export`)| No | Yes | Yes | Yes |
| **Leads: Bulk Import** (`leads:import`)| No | No | Yes | Yes |
| **Tags: Read** (`tags:read`) | Yes | Yes | Yes | Yes |
| **Tags: Write / Delete** (`tags:*`) | No | Yes | Yes | Yes |
| **Stats: Read** (`stats:read`) | Yes | Yes | Yes | Yes |
| **Webhooks: Ingest** (`webhooks:post`)| No | No | Custom / Negotiated | **Yes (Default)** |
| **Wildcard Scope** (`*`) | No | No | No | **Yes (Internal)** |
| **Lead Contact Number** | **Masked (`null`)** | **Unmasked** | **Unmasked** | **Unmasked** |
| **Lead Notes & Sensitive Fields** | Masked / Read-only | Unmasked | Unmasked | Unmasked |
| **Custom Rate Limit Override** | Denied (> tier cap) | Denied (> tier cap) | Denied (> tier cap) | **Allowed (Unlimited / Custom)** |

---

### 5.3 Developer-Level Keys vs. Customer Keys

The system provides two fundamental classes of API keys:

#### 1. Developer-Level Keys (Internal)
- **Origin**: Generated directly within the Laravel Backend GUI (`/dashboard/api-keys`).
- **Owner**: Assigned to an internal developer, administrator, or internal service account (`admin@leadsboard.local`).
- **Tier**: Assigned to the `developer` tier.
- **Capabilities**:
  - Unrestricted access: Can carry the full wildcard `*` scope or specific pipeline scopes (e.g. `webhooks:post`).
  - No field masking: Contact phone numbers, email statuses, and raw payload metadata are always returned in plaintext.
  - High / unconstrained throughput: Rate limit defaults to 2,400 req/min, or can be set to 0 for unlimited pipeline throughput.
  - Integration use cases: Used by n8n automation workflows, Google Sheets ETL scripts, data enrichment workers, and regression test suites.

#### 2. Customer Keys (External)
- **Origin**: Self-served by external users via the React Customer Portal (`/developer`).
- **Owner**: Assigned to the authenticated customer account.
- **Tier**: Strictly locked to the customer's active subscription tier (`free`, `pro`, `enterprise`).
- **Capabilities**:
  - Scope boundaries: Free users cannot select write, delete, or export scopes.
  - Field masking: Free tier users receive `contact_number: null` with `meta.tier_restricted_fields: ["contact_number"]`.
  - Rate limit budgeting: The key rate limit cannot exceed the customer's tier ceiling.
  - Active key quotas: Enforces max keys (Free: 3, Pro: 10, Enterprise: 50).

---

## 6. Granular Scope System

Granular scopes follow a strict `resource:action` naming convention:

| Scope | Resource | Action | Description | Safe for Public Keys? |
| :--- | :--- | :--- | :--- | :--- |
| `leads:read` | `leads` | `read` | Browse and read lead listings | Yes |
| `leads:write` | `leads` | `write` | Create and update lead records | No |
| `leads:delete` | `leads` | `delete` | Soft-delete lead records | No |
| `leads:export` | `leads` | `export` | Export lead datasets to CSV / JSON | Yes |
| `leads:import` | `leads` | `import` | Bulk ingest lead datasets | No |
| `tags:read` | `tags` | `read` | Browse tags and classifications | Yes |
| `tags:write` | `tags` | `write` | Create and update tags | No |
| `tags:delete` | `tags` | `delete` | Remove tags | No |
| `stats:read` | `stats` | `read` | Read dashboard analytics & counts | Yes |
| `webhooks:post` | `webhooks`| `post` | Post incoming payloads to ingestion API | No |
| `*` | `*` | `*` | Full unrestricted administrative access | No (Developer only) |

---

## 7. Key Types & Cryptographic Architecture

API keys use distinct cryptographic prefixes to signal their operational context:

### 7.1 Key Types
1. **Public Keys (`lb_pub_...`)**:
   - Intended for frontend clients, read-only widgets, or client-side SDKs.
   - Strictly forbidden from mutating resources (`leads:write`, `leads:delete`, `webhooks:post`).
2. **Secret Keys (`lb_sec_...`)**:
   - Intended for server-side backend integrations, automation pipelines (n8n), and internal microservices.
   - Allowed full read/write mutations subject to tier and granted scopes.

### 7.2 Cryptographic Generation & Storage
- Plaintext keys are generated using cryptographically secure random bytes: `lb_{type}_{48_random_alphanumeric_chars}`.
- Plaintext keys are shown **only once** upon generation.
- Plaintext keys are **never stored in the database**.
- The database stores only the SHA-256 hash (`hash('sha256', $plainTextKey)`).
- Fast lookup is enabled by indexing the `key` column with SHA-256 binary/hex strings.
- Displayed records show only the plain text prefix (e.g., `lb_sec_n8n...`).

---

## 8. Dual-Bucket Rate Limiting Architecture

The rate limiter (`TierLimiter.php`) implements a two-tier protection mechanism:

1. **Bucket 1: Account-Level Aggregate Limit**  
   Tracks the total requests consumed across *all* active keys belonging to a user account within the rolling 1-minute window. Prevents a user from circumventing plan limits by creating multiple keys.
2. **Bucket 2: Key-Level Budget Limit**  
   Tracks individual key consumption. A customer can create a key with a lower rate limit (e.g., 20 req/min) to sandbox a specific external integration.
3. **Daily Quota Bucket**  
   Tracks aggregate daily calls (e.g., 5,000 for Free; 1,000,000 for Developer).

---

## 9. Field-Level Authorization & Response Masking

Field masking is handled dynamically in Laravel API resources (`LeadResource.php`):

1. The caller's tier is resolved via `ApiKey -> Tier`.
2. Active restrictions are queried from `tier_field_restrictions`.
3. If `contact_number` is restricted (`mask_null`), the API returns:
   ```json
   {
     "id": 101,
     "full_name": "Jane Doe",
     "job_title": "CTO",
     "corporate_email": "jane@example.com",
     "contact_number": null,
     "meta": {
       "tier_restricted_fields": ["contact_number"]
     }
   }
   ```
4. For `pro`, `enterprise`, and `developer` keys, `contact_number` is returned unmasked.

---

## 10. Database Schema (3NF Normalized Architecture)

The database strictly conforms to Third Normal Form (3NF):

```text
tiers
├── id (PK)
├── slug (string, unique) ['free', 'pro', 'enterprise', 'developer']
├── name (string)
├── rate_limit_per_minute (integer)
├── daily_request_limit (integer)
├── max_keys (integer)
├── can_export (boolean)
└── can_bulk_operations (boolean)

api_scopes
├── id (PK)
├── name (string, unique) ['leads:read', 'webhooks:post', '*']
├── resource (string)
├── action (string)
├── description (string)
└── is_mutation (boolean)

tier_scopes (Pivot)
├── tier_id (FK -> tiers.id)
└── api_scope_id (FK -> api_scopes.id)

tier_field_restrictions
├── id (PK)
├── tier_id (FK -> tiers.id)
├── resource (string)
├── field_name (string)
└── restriction_type (string) ['mask_null', 'hidden']

subscriptions
├── id (PK)
├── user_id (FK -> users.id)
├── tier_id (FK -> tiers.id)
├── status (string) ['active', 'cancelled', 'expired']
└── starts_at / ends_at (timestamps)

api_keys
├── id (PK)
├── user_id (FK -> users.id, nullable)
├── tier_id (FK -> tiers.id)
├── name (string)
├── key_type (enum: public, secret)
├── key (string, 64-char sha256, unique, indexed)
├── plain_text_prefix (string, 12-char)
├── rate_limit_per_minute (integer, nullable)
├── allowed_ips (json, nullable)
├── is_active (boolean)
├── last_used_at (timestamp, nullable)
└── expires_at (timestamp, nullable)

api_key_scopes (Pivot)
├── api_key_id (FK -> api_keys.id)
└── api_scope_id (FK -> api_scopes.id)

api_traces (Audit)
├── id (PK)
├── trace_id (uuid, indexed)
├── api_key_id (FK -> api_keys.id, nullable)
├── key_prefix (string, nullable)
├── route_name (string, nullable)
├── http_method (string, 10)
├── url (text)
├── ip_address (string, 45)
├── status_code (integer)
├── duration_ms (float)
├── query_params (json, nullable)
├── request_payload (json, nullable)
└── created_at (timestamp)
```

---

## 11. API Endpoints

### 11.1 Internal Developer & Admin GUI (Laravel)
- `GET  /dashboard/api-keys` — List all system keys with tier badges, prefixes, and active state.
- `POST /dashboard/api-keys` — Generate developer-level keys or customer keys with tier selection.
- `PUT  /dashboard/api-keys/{id}` — Update key name, rate limits, or active state.
- `DELETE /dashboard/api-keys/{id}` — Revoke and delete keys.

### 11.2 Customer Portal REST API (React Frontend)
- `GET  /api/developer/api-keys` — List customer-owned keys + plan entitlements metadata.
- `POST /api/developer/api-keys` — Self-serve a key within tier ceiling.
- `GET  /api/developer/api-keys/{id}` — View single owned key details.
- `PUT  /api/developer/api-keys/{id}` — Update key name.
- `POST /api/developer/api-keys/{id}/rotate` — Rotate plaintext secret.
- `DELETE /api/developer/api-keys/{id}` — Revoke key.
- `GET  /api/developer/traces` — View audit logs for owned keys.

### 11.3 Webhook Ingestion API (Pipelines & n8n)
- `POST /api/v1/webhooks/leads` — Ingest single lead payload (requires `webhooks:post` scope).
- `POST /api/v1/webhooks/leads/bulk` — Ingest bulk lead payload (requires `webhooks:post` scope).

---

## 12. Final Architectural Guiding Principles

1. **Laravel is the Developers' Hub & Business Logic Core**:  
   Laravel handles all database operations, migrations, business services, rate limiting, and internal developer workbench features.
2. **React is the Customer Portal**:  
   React handles client presentation and self-service interactions, strictly adhering to server-dictated tier boundaries.
3. **Developer Keys are First-Class Citizens**:  
   Internal developers and automated pipelines operate under the `developer` tier with unmasked field access, wildcard scope capabilities, and high throughput.
4. **Different Interfaces. Different Authority. One Source of Truth**:  
   Whether an API key is created in the Laravel Developer GUI or the React Customer Portal, all keys are validated against the same normalized 3NF database and authenticated by the same unified middleware.