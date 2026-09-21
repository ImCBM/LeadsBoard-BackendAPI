# LeadsBoard — Backend API Documentation
**Version:** 1.0.0  
**Base URL (Local):** `http://localhost:8000/api/v1`  
**Hostinger Base URL:** `https://your-domain.com/api/v1`

---

## 1. Overview & Architecture

The LeadsBoard backend ingests, normalizes, deduplicates, stores, and serves B2B lead data received primarily from upstream automations (e.g. n8n pipelines, Google Sheets master sync) and external services. It provides:
1. **Webhook Ingestion Endpoints** for upstream automation (n8n) with secret token verification.
2. **Lead CRUD Endpoints** with intermediate search, multi-field filtering, sorting, pagination, and streamed CSV export.
3. **Dashboard Statistics & Summary Endpoints** for metric aggregations.
4. **Flexible Multi-Tier Authentication**:
   - **Webhook Secret Token** (`Authorization: Bearer <WEBHOOK_SECRET>` or `X-Webhook-Token: <token>`) for n8n pipelines.
   - **API Keys** (`X-API-Key: <key>` or `api_key=<key>`) with per-key custom rate limits for external services and client integrations.
   - **Sanctum Auth** (`Authorization: Bearer <sanctum_token>`) for frontend SPAs and user sessions.

---

## 2. Authentication Methods

### Method A: Webhook Secret Token (For n8n Automation)
Used on `/api/v1/webhook/*` routes. Set `WEBHOOK_SECRET` in your `.env` file.

**Headers:**
```http
Authorization: Bearer YOUR_WEBHOOK_SECRET
```
*Or via custom header:*
```http
X-Webhook-Token: YOUR_WEBHOOK_SECRET
```

### Method B: API Key (For Third-Party / External Integrations)
Used on `/api/v1/external/*` routes. Keys are managed in the `api_keys` table and hashed with SHA-256 for security. 

> [!NOTE]
> Each API key has its own **Rate Limit** (requests per minute) configured in the dashboard. If the limit is set to `0`, requests are unlimited.

**Headers:**
```http
X-API-Key: jb_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxx
```
*Or via Bearer:*
```http
Authorization: Bearer jb_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxx
```
*Or via Query Parameter:*
```http
GET /api/v1/external/leads?api_key=jb_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

**Rate Limiting Response (`429 Too Many Requests`):**
If the assigned rate limit is exceeded, the API blocks the request and returns a `429` status along with standard rate limit headers (`Retry-After`, `X-RateLimit-Limit`, `X-RateLimit-Remaining`).
```json
{
  "message": "Rate limit exceeded for this API key.",
  "retry_after_seconds": 15
}
```

### Method C: Sanctum Bearer Token (For Frontend / SPA / Authenticated Users)
Used on protected `/api/v1/*` routes. Obtained via the `/api/v1/auth/login` endpoint.

**Headers:**
```http
Authorization: Bearer 1|xxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

---

## 3. Endpoints Reference

### 3.1. Webhook Ingestion (n8n Target)

#### `POST /api/v1/webhook/leads`
Ingest a single B2B lead. Normalizes names to Title Case, cleans root domains, lowercases emails, validates URLs, resolves country from HQ location, and checks for duplicates on `corporate_email`.

- **Auth:** Webhook Token (`Authorization: Bearer <WEBHOOK_SECRET>`)
- **Headers:** `Content-Type: application/json`

**Payload (Supports n8n-style human-readable keys or snake_case):**
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
  "Employee Headcount": "53",
  "HQ Location": "Aabenraa, Southern Denmark, Denmark"
}
```

**Success Response (`201 Created`):**
```json
{
  "message": "Lead created successfully.",
  "data": {
    "id": 31,
    "full_name": "Frank Mortensen",
    "job_title": "Deputy CEO",
    "title_tier": "C-Level",
    "corporate_email": "frank@sydbank.dk",
    "email_status": "✅ Valid email",
    "company_name": "AL Sydbank",
    "clean_root_domain": "al-sydbank.dk",
    "website_status": "HTTP 200 OK",
    "executive_linkedin_url": "https://www.linkedin.com/in/frank-mortensen62b972/",
    "company_linkedin_page": "https://www.linkedin.com/company/al-sydbank/",
    "industry_classification": "Banking / Financial Services",
    "employee_headcount": 53,
    "hq_location": "Aabenraa, Southern Denmark, Denmark",
    "country": "Denmark",
    "ingestion_channel": "n8n",
    "status": "new",
    "notes": null,
    "created_at": "2026-08-20T18:50:00.000000Z",
    "updated_at": "2026-08-20T18:50:00.000000Z"
  }
}
```

**Duplicate Rejection (`409 Conflict`):**
```json
{
  "message": "Duplicate lead — this email already exists.",
  "errors": {
    "corporate_email": "A lead with this email already exists."
  }
}
```

**Validation Error (`422 Unprocessable Content`):**
```json
{
  "message": "Full name is required.",
  "errors": {
    "Full Name": ["Full name is required."]
  }
}
```

---

#### `POST /api/v1/webhook/leads/bulk`
Ingest an array of up to 500 leads in a single batch request.

- **Auth:** Webhook Token
- **Headers:** `Content-Type: application/json`

**Payload:**
```json
{
  "leads": [
    {
      "Full Name": "Ana Carolina Pinto",
      "Corporate Work Email": "ana.pinto@novatrail.io",
      "Company Name": "NovaTrail",
      "HQ Location": "Porto, Porto, Portugal"
    },
    {
      "Full Name": "Victor Hugo Marques",
      "Corporate Work Email": "victor.marques@luxuryfitco.pt",
      "Company Name": "Luxury Fit Co",
      "HQ Location": "Lisbon, Lisbon, Portugal"
    }
  ]
}
```

**Success Response (`201 Created` or `207 Multi-Status`):**
```json
{
  "message": "Bulk import complete: 2 inserted, 0 duplicates, 0 errors.",
  "summary": {
    "inserted": 2,
    "duplicates": 0,
    "errors": 0,
    "total": 2
  },
  "results": [
    {
      "index": 0,
      "success": true,
      "duplicate": false,
      "errors": [],
      "lead_id": 32
    },
    {
      "index": 1,
      "success": true,
      "duplicate": false,
      "errors": [],
      "lead_id": 33
    }
  ]
}
```

---

#### `POST /api/v1/webhook/leads/bulk-delete`
Bulk delete leads via webhook by IDs, emails, tags, or ingestion channel.

- **Auth:** Webhook Token (`Authorization: Bearer <WEBHOOK_SECRET>`)
- **Headers:** `Content-Type: application/json`

**Payload Examples:**
```json
// Option A: Delete by explicit lead IDs
{
  "lead_ids": [101, 102, 103]
}

// Option B: Delete by Tag (e.g., wipe test leads)
{
  "tag": "test"
}

// Option C: Delete by Email list
{
  "emails": ["test1@example.com", "test2@example.com"]
}

// Option D: Delete by Channel and Status
{
  "channel": "n8n",
  "status": "rejected"
}
```

**Response (`200 OK`):**
```json
{
  "message": "Bulk delete complete: 3 leads deleted.",
  "deleted_count": 3,
  "deleted_ids": [101, 102, 103],
  "criteria": {
    "tag": "test"
  }
}
```

---

#### `POST /api/v1/webhook/leads/bulk-tag`
Bulk assign or remove tags from multiple leads in a single request.

- **Auth:** Webhook Token (`Authorization: Bearer <WEBHOOK_SECRET>`)
- **Headers:** `Content-Type: application/json`

**Payload:**
```json
{
  "lead_ids": [101, 102, 103],
  "add_tags": ["vip", "q3-outreach"],
  "remove_tags": ["sample"]
}
```

**Response (`200 OK`):**
```json
{
  "message": "Bulk tag complete: 3 leads updated.",
  "updated_count": 3,
  "lead_ids": [101, 102, 103],
  "operations": {
    "added": ["vip", "q3-outreach"],
    "removed": ["sample"],
    "synced": null
  }
}
```

---

### 3.2. Authentication (Sanctum)

#### `POST /api/v1/auth/login`
- **Auth:** Public
- **Body:** `{"email": "admin@leadsboard.local", "password": "password"}`

**Response (`200 OK`):**
```json
{
  "message": "Login successful.",
  "data": {
    "token": "1|N4B7U9...sanctum_token...",
    "user": {
      "id": 1,
      "name": "Admin",
      "email": "admin@leadsboard.local",
      "role": "admin"
    }
  }
}
```

#### `GET /api/v1/auth/me`
- **Auth:** Sanctum Bearer Token
- **Response (`200 OK`):** Returns authenticated user profile.

#### `POST /api/v1/auth/logout`
- **Auth:** Sanctum Bearer Token
- **Response (`200 OK`):** Revokes current access token.

---

### 3.3. Leads Management (CRUD & Filtering)

#### `GET /api/v1/leads` or `GET /api/v1/external/leads`
List paginated leads with advanced filters and search.

- **Auth:** Sanctum Bearer Token OR API Key (on `/external/leads`)
- **Query Parameters:**

| Parameter | Type | Example | Description |
|-----------|------|---------|-------------|
| `search` | `string` | `Tech` | Searches Name, Email, Company, Title, Industry, Location, Country |
| `industry` | `string` | `Real Estate` | Exact match on `industry_classification` |
| `title_tier` | `string` | `C-Level` | Filter by `C-Level`, `VP-Level`, `Director-Level`, `Other` |
| `status` | `string` | `new` | Filter by `new`, `reviewed`, `qualified`, `rejected` |
| `country` | `string` | `Portugal` | Filter by country name |
| `ingestion_channel` | `string` | `n8n` | Filter by `n8n`, `manual`, `api`, `csv_import` |
| `tag` | `string` | `test` | Filter by single tag name or slug |
| `tags` | `string` | `test,demo` | Filter by multiple tags (comma-separated or array) |
| `date_from` | `date (YYYY-MM-DD)` | `2026-08-01` | Filter created on or after date |
| `date_to` | `date (YYYY-MM-DD)` | `2026-08-31` | Filter created on or before date |
| `per_page` | `integer` | `25` | Results per page (max: 100, default: 25) |
| `sort_by` | `string` | `created_at` | Sort field (`id`, `full_name`, `company_name`, `industry_classification`, `title_tier`, `status`, `country`, `created_at`, `employee_headcount`) |
| `sort_dir` | `string` | `desc` | Sort direction (`asc` or `desc`) |

**Response (`200 OK`):**
```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1,
      "full_name": "Daniel Whitfield",
      "job_title": "President",
      "title_tier": "C-Level",
      "corporate_email": "d.whitfield@brightharbor.com",
      "email_status": "✔ Valid email",
      "company_name": "Bright Harbor Group",
      "clean_root_domain": "brightharbor.com",
      "website_status": "✔ HTTP 200 OK",
      "executive_linkedin_url": "https://www.linkedin.com/in/danielwhitfield",
      "company_linkedin_page": "https://www.linkedin.com/company/brightharbor",
      "industry_classification": "Real Estate",
      "employee_headcount": 42,
      "hq_location": "Lisbon, Lisbon, Portugal",
      "country": "Portugal",
      "ingestion_channel": "csv_import",
      "status": "new",
      "notes": null,
      "tag_names": ["VIP", "Q3 Campaign"],
      "tags": [
        { "id": 1, "name": "VIP", "slug": "vip", "type": "public", "color": "#10b981" }
      ],
      "created_at": "2026-08-20T17:58:00.000000Z",
      "updated_at": "2026-08-20T17:58:00.000000Z"
    }
  ],
  "first_page_url": "http://localhost:8000/api/v1/leads?page=1",
  "from": 1,
  "last_page": 2,
  "last_page_url": "http://localhost:8000/api/v1/leads?page=2",
  "per_page": 25,
  "total": 30
}
```

---

#### `POST /api/v1/leads/bulk-delete` or `DELETE /api/v1/leads/bulk`
Bulk delete leads by IDs, emails, tag, or channel.

- **Auth:** Sanctum Token OR API Key
- **Body:**
```json
{
  "lead_ids": [10, 11, 12]
}
// OR
{
  "tag": "test"
}
```

---

#### `POST /api/v1/leads/bulk-tag`
Bulk assign or remove tags on selected leads.

- **Auth:** Sanctum Token OR API Key
- **Body:**
```json
{
  "lead_ids": [10, 11, 12],
  "add_tags": ["vip", "high-priority"],
  "remove_tags": ["sample"]
}
```

---

#### `GET /api/v1/leads/{id}` or `GET /api/v1/external/leads/{id}`
- **Auth:** Sanctum Token OR API Key
- **Response (`200 OK`):** Returns single lead record.

#### `POST /api/v1/leads`
- **Auth:** Sanctum Token
- **Body:** Same payload structure as webhook endpoint. Sets `ingestion_channel = "api"`. Accepts `"tags": ["test"]`.
- **Response (`201 Created` / `409 Conflict` / `422 Unprocessable Content`).

#### `PUT /api/v1/leads/{id}`
Update lead details, classification, status, review notes, or tags.

- **Auth:** Sanctum Token
- **Body:**
```json
{
  "status": "qualified",
  "notes": "Spoke on phone, scheduled follow up for next Tuesday.",
  "tags": ["vip", "reviewed"]
}
```
- **Response (`200 OK`):** Returns updated lead data.

#### `DELETE /api/v1/leads/{id}`
- **Auth:** Sanctum Token
- **Response (`200 OK`):** `{"message": "Lead deleted successfully."}`

---

#### `GET /api/v1/leads/export/csv` or `GET /api/v1/external/leads/export/csv`
Streamed CSV download of leads with filters applied (supports `tag` and `tags`).

---

### 3.4. Tags Management Endpoints

#### `GET /api/v1/tags`
List all tags with `leads_count`. Supports `?type=public` or `?type=system` and `?search=term`.

- **Auth:** Sanctum Token OR API Key

#### `POST /api/v1/tags`
Create a new custom tag.

- **Auth:** Sanctum Token
- **Body:**
```json
{
  "name": "Enterprise Strategic",
  "type": "public",
  "color": "#6366f1",
  "description": "Tier 1 Enterprise accounts"
}
```

#### `DELETE /api/v1/tags/{id}`
Delete a tag. Automatically unlinks from all associated leads.

- **Auth:** Sanctum Token

---

### 3.5. Developer CLI Maintenance & Retroactive Tagging

#### 1. Retroactively Tag Existing Leads (`php artisan leads:tag`)
Tag existing leads in the database by ingestion channel, company name pattern, email domain, or IDs:
```bash
# Tag all leads ingested from n8n as 'test':
php artisan leads:tag --channel=n8n --tag=test

# Tag leads with company containing 'Test':
php artisan leads:tag --company-pattern="%Test%" --tag=test

# Tag specific lead IDs:
php artisan leads:tag --ids=1,2,3,4,5 --tag=vip --type=public

# Preview matching leads without writing to DB:
php artisan leads:tag --channel=n8n --dry-run
```

#### 2. Cleanup & Wipe Test Leads (`php artisan leads:cleanup-test`)
Quickly wipe test leads from the database:
```bash
# Wipe all leads with 'test' tag:
php artisan leads:cleanup-test --tag=test

# Preview which leads will be deleted:
php artisan leads:cleanup-test --tag=test --dry-run

# Force deletion without confirmation prompt:
php artisan leads:cleanup-test --tag=test --force
```

---

#### `GET /api/v1/leads/filters` or `GET /api/v1/external/leads/filters`
Fetch distinct available filter options to dynamically populate UI dropdowns.

- **Auth:** Sanctum Token OR API Key
- **Response (`200 OK`):**
```json
{
  "industries": [
    "Artificial Intelligence",
    "Banking / Financial Services",
    "Cybersecurity",
    "Real Estate",
    "Software"
  ],
  "title_tiers": ["C-Level", "VP-Level", "Director-Level", "Other"],
  "statuses": ["new", "reviewed", "qualified", "rejected"],
  "countries": ["Austria", "Denmark", "Iceland", "India", "Ireland", "Portugal", "Spain"],
  "channels": ["n8n", "manual", "api", "csv_import"]
}
```

---

### 3.4. Dashboard Statistics & Summaries

#### `GET /api/v1/stats/summary` or `GET /api/v1/external/stats/summary`
- **Auth:** Sanctum Token OR API Key
- **Response (`200 OK`):**
```json
{
  "data": {
    "total_leads": 30,
    "today": 30,
    "this_week": 30,
    "this_month": 30,
    "status_counts": {
      "new": 30,
      "reviewed": 0,
      "qualified": 0,
      "rejected": 0
    }
  }
}
```

#### `GET /api/v1/stats/by-industry`
- **Response (`200 OK`):** `[{"industry_classification": "Real Estate", "count": 2}, ...]`

#### `GET /api/v1/stats/by-title-tier`
- **Response (`200 OK`):** `[{"title_tier": "C-Level", "count": 28}, {"title_tier": "Director-Level", "count": 2}]`

#### `GET /api/v1/stats/by-status`
- **Response (`200 OK`):** `[{"status": "new", "count": 30}]`

#### `GET /api/v1/stats/by-country`
- **Response (`200 OK`):** `[{"country": "Portugal", "count": 17}, {"country": "Austria", "count": 4}, ...]`

#### `GET /api/v1/stats/timeline?days=30`
- **Query Parameter:** `days` (integer, default: 30, max: 365)
- **Response (`200 OK`):** `{"data": [{"date": "2026-08-20", "count": 30}], "range": {"from": "2026-07-21", "to": "2026-08-20", "days": 30}}`

---

## 4. Web Dashboard UI

The application includes a minimal, responsive Blade web dashboard built according to the **Fresh Minimalism** design specification.

- **URL:** `http://localhost:8000/dashboard` (or `https://your-domain.com/dashboard`)
- **Default Admin Login:**
  - **Email:** `admin@leadsboard.local`
  - **Password:** `password` (Note: In local dev, the login form is pre-filled to bypass manual entry).
- **Features:**
  - Top metric summary cards (Total Leads, Today, This Week, This Month).
  - Search input with debounce support.
  - Multi-select filters for Industry, Title Tier, Status, Country, and Date Range. All filters can be combined using `AND` logic.
  - Interactive table with **clickable column headers** to toggle A-Z and Z-A sorting dynamically.
  - One-click CSV Export retaining active filter criteria and sort order.
  - Paginated navigation.

### 4.1. API Key Management
A dedicated **🔑 API Keys** section (`/dashboard/api-keys`) is available in the dashboard for managing integration access securely.
- **Generate:** Create new keys with a human-readable label. The plain-text key is shown exactly *once* for security.
- **Rate Limits:** You can edit any key to apply dynamic rate limits (e.g., `60` req/min, or `0` for unlimited). These limits are instantly enforced by the `ValidateApiKey` middleware on external API routes.
- **Status & Revocation:** Keys can be temporarily deactivated (unchecked) or permanently revoked and deleted from the database.

---

## 5. Hostinger Shared Hosting Deployment Guide

### Prerequisites
1. Hostinger cPanel / hPanel with PHP 8.2+ and MySQL database.
2. SSH or Git repository access on Hostinger.

### Step-by-Step Setup
1. **Upload Files & Point Document Root**:
   - In hPanel, set the Domain Document Root to the project's `public/` directory (e.g. `/home/u123456789/public_html/public` or move `public` files into `public_html` and adjust paths).
2. **Environment Configuration**:
   - Create a MySQL database and user in hPanel.
   - Copy `.env.example` to `.env` on Hostinger:
     ```env
     APP_NAME=LeadsBoard
     APP_ENV=production
     APP_DEBUG=false
     APP_URL=https://your-domain.com

     DB_CONNECTION=mysql
     DB_HOST=127.0.0.1
     DB_PORT=3306
     DB_DATABASE=u123456789_leadsboard
     DB_USERNAME=u123456789_leadsuser
     DB_PASSWORD=YourStrongDatabasePassword

     WEBHOOK_SECRET=generate_a_secure_64_char_secret_here
     LEADS_PER_PAGE=25
     API_DEFAULT_RATE_LIMIT=120
     ```
3. **Generate Key & Run Migrations**:
   ```bash
   php artisan key:generate --force
   php artisan migrate --seed --force
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
4. **n8n Configuration**:
   - In the n8n HTTP Request node:
     - **Method:** `POST`
     - **URL:** `https://your-domain.com/api/v1/webhook/leads`
     - **Authentication:** Generic Credential Header / Header Auth
     - **Header Name:** `Authorization`
     - **Header Value:** `Bearer YOUR_CONFIGURED_WEBHOOK_SECRET`
