# API Reference

> **Audience**: Developers integrating with the API, n8n operators configuring webhooks, and external consumers.
>
> **Base URL**: All API routes are prefixed with `/api/v1/`.

---

## Table of Contents

- [Authentication](#authentication)
  - [Webhook Token (n8n)](#1-webhook-token-for-n8n-and-automation)
  - [Sanctum Token (Frontend)](#2-sanctum-token-for-the-frontend-dashboard)
  - [API Key (External)](#3-api-key-for-external-consumers)
- [Webhook Routes](#webhook-routes-n8n--automation)
- [Auth Routes](#auth-routes)
- [Lead Routes](#lead-routes-sanctum-protected)
- [Tag Routes](#tag-routes-sanctum-protected)
- [Stats Routes](#stats-routes-sanctum-protected)
- [External Routes](#external-routes-api-key-protected)
- [Filter Parameters](#filter-parameters)
- [Error Codes & Troubleshooting](#error-codes--troubleshooting)

---

## Authentication

### 1. Webhook Token (for n8n and Automation)

**Who uses this**: n8n workflows, external automation scripts.

The webhook secret is a **static token** defined in the `WEBHOOK_SECRET` environment variable. It is the **same value** across staging (Render) and production (Hostinger).

**How to send it** (choose one):

```
Authorization: Bearer b2bleadscraper-prod-webhook-token-7f9a2e8c4d1b
```

```
X-Webhook-Token: b2bleadscraper-prod-webhook-token-7f9a2e8c4d1b
```

> **For n8n operators**: In your HTTP Request node, set the header `Authorization` to `Bearer <your-token>`. That's it.

**What happens if it's wrong**:
- Missing or invalid → `401 Unauthorized`
- Webhook secret not configured on server → `503 Service Unavailable`

---

### 2. Sanctum Token (for the Frontend Dashboard)

**Who uses this**: The React frontend dashboard.

1. **Login** by POSTing credentials to `/api/v1/auth/login`
2. **Receive** a bearer token in the response
3. **Use** the token on all subsequent requests: `Authorization: Bearer <sanctum-token>`

Default admin credentials (seeded on first deploy):
- Email: `admin@leadsboard.local`
- Password: `password`

> ⚠️ **Change these immediately in production.**

---

### 3. API Key (for External Consumers)

**Who uses this**: Third-party tools, partner integrations, external dashboards.

API keys are created and managed via the web dashboard at `/dashboard/api-keys`. Each key:
- Is **hashed (SHA-256)** before storage — the plain-text key is shown **only once** at creation
- Can have a custom **rate limit** (requests per minute), or unlimited (0)
- Can be **activated/deactivated** without deletion
- Can have an **expiration date**

**How to send it** (choose one):

```
Authorization: Bearer <your-api-key>
```

```
X-API-Key: <your-api-key>
```

```
GET /api/v1/external/leads?api_key=<your-api-key>
```

**Rate limit headers** (when rate-limited):

| Header | Meaning |
|---|---|
| `X-RateLimit-Limit` | Your requests-per-minute limit |
| `X-RateLimit-Remaining` | Requests remaining in this window |
| `Retry-After` | Seconds until limit resets |

---

## Webhook Routes (n8n / Automation)

All webhook routes require **Webhook Token** authentication.

### POST `/api/v1/webhook/leads` — Ingest a Single Lead

Creates one lead in the system. This is the primary endpoint for n8n to send scraped leads.

**Request body** (accepts both n8n-style and snake_case field names):

| Field (n8n-style) | Field (snake_case) | Type | Required | Description |
|---|---|---|---|---|
| `Full Name` | `full_name` | string | ✅ Yes | Person's full name |
| `Corporate Work Email` | `corporate_email` | string (email) | ✅ Yes | Work email (**unique identifier** — duplicates are rejected) |
| `Company Name` | `company_name` | string | ✅ Yes | Company name |
| `Job Title` | `job_title` | string | No | Person's job title |
| `Title Tier` | `title_tier` | string | No | `C-Level`, `VP-Level`, `Director-Level`, or `Other` (auto-normalized) |
| `Email Status` | `email_status` | string | No | Verification result (e.g., `"✅ Valid email"`) |
| `Clean Root Domain` | `clean_root_domain` | string | No | Company website domain (auto-cleaned: strips `http://`, `www.`) |
| `Website Status` | `website_status` | string | No | HTTP status of company website (e.g., `"HTTP 200 OK"`) |
| `Executive LinkedIn URL` | `executive_linkedin_url` | string (URL) | No | Person's LinkedIn profile URL |
| `Company LinkedIn Page` | `company_linkedin_page` | string (URL) | No | Company's LinkedIn page URL |
| `Industry Classification` | `industry_classification` | string | No | Industry name (auto-created if new) |
| `Employee Headcount` | `employee_headcount` | integer/string | No | Number of employees (non-numeric chars stripped) |
| `HQ Location` | `hq_location` | string | No | Location string like `"Aabenraa, Southern Denmark, Denmark"` |
| — | `country` | string | No | Country name (auto-extracted from HQ Location if not provided) |
| `Tags` / `Tag` | `tags` | string/array | No | Comma-separated tag names or array of names/IDs |
| — | `status` | string | No | Lead status: `new` (default), `reviewed`, `qualified`, `rejected` |
| — | `notes` | string | No | Freeform notes |

**Example request (n8n-style)**:

```json
{
  "Full Name": "Frank Mortensen",
  "Job Title": "Deputy CEO",
  "Title Tier": "C-Level",
  "Corporate Work Email": "frank@al-sydbank.dk",
  "Email Status": "✅ Valid email",
  "Company Name": "AL Sydbank",
  "Clean Root Domain": "al-sydbank.dk",
  "Website Status": "HTTP 200 OK",
  "Executive LinkedIn URL": "https://linkedin.com/in/frank-mortensen",
  "Company LinkedIn Page": "https://linkedin.com/company/al-sydbank",
  "Industry Classification": "Banking / Financial Services",
  "Employee Headcount": "53",
  "HQ Location": "Aabenraa, Southern Denmark, Denmark",
  "Tags": "VIP, Q3 Campaign"
}
```

**Success response** (`201 Created`):

```json
{
  "message": "Lead created successfully.",
  "data": {
    "id": 42,
    "full_name": "Frank Mortensen",
    "corporate_email": "frank@al-sydbank.dk",
    "company_name": "AL Sydbank",
    "country": "Denmark",
    "ingestion_channel": "n8n",
    "status": "new",
    "tag_names": ["VIP", "Q3 Campaign"],
    "..."
  }
}
```

**Error responses**:

| Status | When | Response |
|---|---|---|
| `409 Conflict` | Email already exists | `{"message": "Duplicate lead — this email already exists."}` |
| `422 Unprocessable` | Required fields missing | `{"message": "...", "errors": {"Full Name": [...]}}` |
| `401 Unauthorized` | Invalid/missing webhook token | `{"message": "Invalid or missing webhook token."}` |

---

### POST `/api/v1/webhook/leads/bulk` — Bulk Ingest Leads

Creates multiple leads at once. Used for batch imports from n8n.

**Request body**:

```json
{
  "leads": [
    {
      "Full Name": "Lead One",
      "Corporate Work Email": "lead1@example.com",
      "Company Name": "Corp One"
    },
    {
      "Full Name": "Lead Two",
      "Corporate Work Email": "lead2@example.com",
      "Company Name": "Corp Two"
    }
  ]
}
```

**Limits**: Max 500 leads per batch.

**Response** (`201` if all succeeded, `207 Multi-Status` if some failed):

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
    {"index": 0, "success": true, "duplicate": false, "errors": [], "lead_id": 43},
    {"index": 1, "success": true, "duplicate": false, "errors": [], "lead_id": 44}
  ]
}
```

---

### POST `/api/v1/webhook/leads/bulk-delete` — Bulk Delete via Webhook

Deletes leads matching criteria. Useful for n8n cleanup workflows.

**Request body** (provide at least one selector):

| Field | Type | Description |
|---|---|---|
| `lead_ids` or `ids` | int[] | Specific lead IDs to delete |
| `emails` | string[] | Specific email addresses to match |
| `tag` | string | Delete all leads with this tag |
| `tags` | string[] | Delete all leads with any of these tags |
| `channel` or `ingestion_channel` | string | Delete leads from this channel (`n8n`, `api`, `csv_import`, `manual`) |
| `status` | string | Delete leads with this status |
| `date_from` / `date_to` | date (Y-m-d) | Delete leads created in this date range |
| `confirm` | boolean | If no selector is given, must be `true` to delete all |

**Response** (`200`):

```json
{
  "message": "Bulk delete complete: 15 leads deleted.",
  "deleted_count": 15,
  "deleted_ids": [1, 2, 3, "..."],
  "criteria": {"tag": "test"}
}
```

---

### POST `/api/v1/webhook/leads/bulk-tag` — Bulk Tag via Webhook

Apply, remove, or sync tags across multiple leads.

**Request body**:

| Field | Type | Description |
|---|---|---|
| **Target selectors** (at least one required): | | |
| `lead_ids` or `ids` | int[] | Specific lead IDs |
| `emails` | string[] | Target by email address |
| `filter_tag` | string | Target all leads that have this tag |
| `channel` / `ingestion_channel` | string | Target leads from this channel |
| **Tag operations** (at least one required): | | |
| `add_tags` or `tags` | string[] | Tags to add (without removing existing) |
| `remove_tags` | string[] | Tags to remove |
| `sync_tags` | string[] | Replace all tags with exactly these (overwrite mode) |

**Example** — add "VIP" to leads 1, 2, 3:

```json
{
  "lead_ids": [1, 2, 3],
  "add_tags": ["VIP"]
}
```

**Example** — remove "Test" tag from all n8n leads:

```json
{
  "channel": "n8n",
  "remove_tags": ["Test"]
}
```

**Response** (`200`):

```json
{
  "message": "Bulk tag complete: 3 leads updated.",
  "updated_count": 3,
  "lead_ids": [1, 2, 3],
  "operations": {
    "added": ["VIP"],
    "removed": null,
    "synced": null
  }
}
```

---

## Auth Routes

### POST `/api/v1/auth/login` — Login *(Public)*

**Request**:

```json
{
  "email": "admin@leadsboard.local",
  "password": "password"
}
```

**Response** (`200`):

```json
{
  "message": "Login successful.",
  "data": {
    "token": "1|abc123def456...",
    "user": {
      "id": 1,
      "name": "Admin",
      "email": "admin@leadsboard.local",
      "role": "admin"
    }
  }
}
```

**Error**: `401` with `{"message": "Invalid credentials."}`

---

### POST `/api/v1/auth/logout` — Logout *(Sanctum)*

Revokes the current bearer token.

**Response** (`200`): `{"message": "Logged out successfully."}`

---

### GET `/api/v1/auth/me` — Get Current User *(Sanctum)*

**Response** (`200`):

```json
{
  "data": {
    "id": 1,
    "name": "Admin",
    "email": "admin@leadsboard.local",
    "role": "admin"
  }
}
```

---

## Lead Routes (Sanctum-Protected)

All require `Authorization: Bearer <sanctum-token>`.

### GET `/api/v1/leads` — List Leads (Paginated)

Returns a paginated, filterable list of leads with all related data.

**Query parameters**:

| Param | Type | Default | Description |
|---|---|---|---|
| `per_page` | int | 25 | Results per page (max 100) |
| `sort_by` | string | `created_at` | Sort column (see below) |
| `sort_dir` | string | `desc` | Sort direction: `asc` or `desc` |
| *[Filter params]* | | | See [Filter Parameters](#filter-parameters) |

**Sortable columns**: `id`, `full_name`, `job_title`, `title_tier`, `corporate_email`, `email_status`, `status`, `ingestion_channel`, `created_at`, `company_name`, `clean_root_domain`, `website_status`, `industry_classification`, `country`, `employee_headcount`

**Response** (`200`): Standard Laravel paginated response with `data`, `links`, `meta`.

---

### GET `/api/v1/leads/{id}` — Get Single Lead

**Response** (`200`):

```json
{
  "data": {
    "id": 42,
    "full_name": "Frank Mortensen",
    "job_title": "Deputy CEO",
    "title_tier": "C-Level",
    "corporate_email": "frank@al-sydbank.dk",
    "email_status": "✅ Valid email",
    "executive_linkedin_url": "https://linkedin.com/in/frank-mortensen",
    "ingestion_channel": "n8n",
    "status": "new",
    "notes": null,
    "company_name": "AL Sydbank",
    "clean_root_domain": "al-sydbank.dk",
    "website_status": "HTTP 200 OK",
    "company_linkedin_page": "https://linkedin.com/company/al-sydbank",
    "industry_classification": "Banking / Financial Services",
    "employee_headcount": 53,
    "hq_location": "Aabenraa, Southern Denmark, Denmark",
    "country": "Denmark",
    "tag_names": ["VIP", "Q3 Campaign"],
    "tags": [
      {"id": 4, "name": "VIP", "slug": "vip", "type": "public", "color": "#10b981"}
    ],
    "company": { "..." },
    "created_at": "2026-09-01T10:30:00.000000Z"
  }
}
```

**Error**: `404` if not found.

---

### POST `/api/v1/leads` — Create a Lead

Same payload as the webhook single-lead endpoint. Channel is recorded as `api` instead of `n8n`.

---

### PUT `/api/v1/leads/{id}` — Update a Lead

All fields are optional (send only what you want to change).

**Request body**:

| Field | Type | Validation |
|---|---|---|
| `full_name` | string | max 255 |
| `job_title` | string | max 255 |
| `title_tier` | string | Must be one of: `C-Level`, `VP-Level`, `Director-Level`, `Other` |
| `corporate_email` | email | Must be unique (excluding current lead) |
| `email_status` | string | max 100 |
| `company_name` | string | max 255 |
| `clean_root_domain` | string | max 255 |
| `website_status` | string | max 100 |
| `executive_linkedin_url` | URL | max 500 |
| `company_linkedin_page` | URL | max 500 |
| `industry_classification` | string | max 255 |
| `employee_headcount` | integer | min 0 |
| `hq_location` | string | max 500 |
| `country` | string | max 255 |
| `status` | string | Must be one of: `new`, `reviewed`, `qualified`, `rejected` |
| `notes` | string | max 5000 |
| `tags` | array/string | Tag names, IDs, or objects |

**Response** (`200`):

```json
{
  "message": "Lead updated successfully.",
  "data": { "..." }
}
```

---

### DELETE `/api/v1/leads/{id}` — Delete a Lead

**Response** (`200`): `{"message": "Lead deleted successfully."}`

---

### POST `/api/v1/leads/bulk-delete` — Bulk Delete Leads

Same payload and behavior as the webhook bulk-delete endpoint.

### DELETE `/api/v1/leads/bulk` — Bulk Delete (Alternate Method)

Same as above, using DELETE method instead of POST.

### POST `/api/v1/leads/bulk-tag` — Bulk Tag Leads

Same payload and behavior as the webhook bulk-tag endpoint.

---

### GET `/api/v1/leads/export/csv` — Export Leads as CSV

Downloads a CSV file of filtered leads. Accepts all [filter parameters](#filter-parameters) as query params.

**Response**: `text/csv` file download. UTF-8 with BOM for Excel compatibility.

**CSV columns**: ID, Full Name, Job Title, Title Tier, Corporate Email, Email Status, Company Name, Clean Root Domain, Website Status, Executive LinkedIn URL, Company LinkedIn Page, Industry Classification, Employee Headcount, HQ Location, Country, Ingestion Channel, Status, Notes, Created At

---

### GET `/api/v1/leads/filters` — Get Available Filter Options

Returns all valid filter values for building dropdown menus in the UI.

**Response** (`200`):

```json
{
  "industries": ["Banking / Financial Services", "IT / Technology", "..."],
  "title_tiers": ["C-Level", "VP-Level", "Director-Level", "Other"],
  "statuses": ["new", "reviewed", "qualified", "rejected"],
  "countries": ["Denmark", "Germany", "Ireland", "..."],
  "channels": ["n8n", "manual", "api", "csv_import"],
  "tags": [
    {"id": 1, "name": "Test", "slug": "test", "type": "system", "color": "#ef4444"},
    {"id": 4, "name": "VIP", "slug": "vip", "type": "public", "color": "#10b981"}
  ],
  "headcount_ranges": {
    "1-10": "1 – 10 (Startup / Micro)",
    "11-50": "11 – 50 (Small Team)",
    "51-200": "51 – 200 (Mid-Market)",
    "201-500": "201 – 500 (Upper Mid-Market)",
    "501-1000": "501 – 1,000 (Large)",
    "1000+": "1,000+ (Enterprise)"
  },
  "website_statuses": [
    {"value": "200", "label": "Active Website (200 OK)"},
    {"value": "error", "label": "Issues / Unreachable"}
  ],
  "email_statuses": [
    {"value": "valid", "label": "Verified / Valid Email"},
    {"value": "invalid", "label": "Invalid / Catch-all"}
  ]
}
```

---

## Tag Routes (Sanctum-Protected)

### GET `/api/v1/tags` — List All Tags

**Query parameters**:

| Param | Type | Description |
|---|---|---|
| `type` | string | Filter by `public` or `system` |
| `search` | string | Search tag name, slug, or description |

**Response**: `{"data": [{"id": 1, "name": "VIP", "slug": "vip", "type": "public", "color": "#10b981", "leads_count": 23}, "..."]}`

### POST `/api/v1/tags` — Create a Tag

**Request**:

```json
{
  "name": "Hot Lead",
  "type": "public",
  "color": "#ef4444",
  "description": "Leads flagged for immediate follow-up"
}
```

| Field | Required | Validation |
|---|---|---|
| `name` | ✅ Yes | Unique, max 100 chars |
| `type` | No | `public` (default) or `system` |
| `color` | No | Hex color code (e.g., `#ef4444`) |
| `description` | No | Max 255 chars |

### GET `/api/v1/tags/{id}` — Get Single Tag

### DELETE `/api/v1/tags/{id}` — Delete a Tag

---

## Stats Routes (Sanctum-Protected)

### GET `/api/v1/stats/summary` — Dashboard Summary

```json
{
  "data": {
    "total_leads": 1234,
    "today": 5,
    "this_week": 42,
    "this_month": 180,
    "status_counts": {
      "new": 800,
      "reviewed": 200,
      "qualified": 150,
      "rejected": 84
    }
  }
}
```

### GET `/api/v1/stats/by-industry` — Leads by Industry

### GET `/api/v1/stats/by-title-tier` — Leads by Title Tier

### GET `/api/v1/stats/by-status` — Leads by Status

### GET `/api/v1/stats/by-country` — Leads by Country

### GET `/api/v1/stats/timeline` — Lead Timeline

**Query parameter**: `days` (int, default 30, max 365) — how many days of history to return.

```json
{
  "data": [
    {"date": "2026-09-01", "count": 15},
    {"date": "2026-09-02", "count": 23}
  ],
  "range": {"from": "2026-08-10", "to": "2026-09-09", "days": 30}
}
```

---

## External Routes (API Key-Protected)

These routes are for third-party consumers. They are **read-only** plus bulk operations.

| Method | Route | Maps To |
|---|---|---|
| GET | `/api/v1/external/leads` | Same as `GET /api/v1/leads` |
| GET | `/api/v1/external/leads/export/csv` | Same as CSV export |
| GET | `/api/v1/external/leads/filters` | Same as filter options |
| GET | `/api/v1/external/leads/{id}` | Same as single lead view |
| POST | `/api/v1/external/leads/bulk-delete` | Same as bulk delete |
| POST | `/api/v1/external/leads/bulk-tag` | Same as bulk tag |
| GET | `/api/v1/external/tags` | List all tags |
| GET | `/api/v1/external/stats/summary` | Dashboard summary stats |

---

## Filter Parameters

These query parameters work on **any** endpoint that lists or exports leads:

| Param | Type | Description | Example |
|---|---|---|---|
| `search` | string | Multi-token search across name, email, company, domain, title, industry, location, country, tags, status | `search=Frank` |
| `industry` | string | Exact industry name | `industry=Banking / Financial Services` |
| `title_tier` | string | Exact tier value | `title_tier=C-Level` |
| `status` | string | Lead status | `status=new` |
| `country` | string | Country name(s), comma-separated | `country=Denmark,Germany` |
| `ingestion_channel` | string | Source channel | `ingestion_channel=n8n` |
| `tag` | string | Single tag name or slug | `tag=vip` |
| `tags` | string/array | Multiple tags (comma-separated string or array) | `tags=vip,high-priority` |
| `headcount_range` | string | Predefined range | `headcount_range=51-200` |
| `headcount_min` | int | Minimum headcount | `headcount_min=50` |
| `headcount_max` | int | Maximum headcount | `headcount_max=200` |
| `website_status` | string | `200` (active) or `error` (issues) | `website_status=200` |
| `email_status` | string | `valid` or `invalid` | `email_status=valid` |
| `date_from` | date | Leads created on or after this date (Y-m-d) | `date_from=2026-09-01` |
| `date_to` | date | Leads created on or before this date (Y-m-d) | `date_to=2026-09-30` |

**Search behavior**: The `search` parameter tokenizes by comma. Each token must match somewhere across all searchable fields (AND logic between tokens). So `search=Frank,Denmark` means "must match Frank AND Denmark".

---

## Error Codes & Troubleshooting

### HTTP Status Codes

| Code | Meaning | When You'll See It |
|---|---|---|
| `200 OK` | Success | Reads, updates, deletes |
| `201 Created` | Resource created | Creating a lead or tag |
| `207 Multi-Status` | Partial success | Bulk import where some leads failed |
| `401 Unauthorized` | Auth failed | Missing/invalid token, API key, or credentials |
| `404 Not Found` | Resource doesn't exist | Invalid lead or tag ID |
| `409 Conflict` | Duplicate | Lead with this email already exists |
| `422 Unprocessable Entity` | Validation failed | Missing required fields or invalid data |
| `429 Too Many Requests` | Rate limited | API key exceeded its rate limit |
| `500 Internal Server Error` | Server crash | Bug in the code or database issue |
| `503 Service Unavailable` | Webhook secret not configured | Server has no `WEBHOOK_SECRET` set |

### Common Problems & Fixes

#### "Invalid or missing webhook token" (401)

**Cause**: The token you're sending doesn't match `WEBHOOK_SECRET` in the server's `.env`.

**Fix**:
1. Check that your n8n HTTP node has `Authorization: Bearer <token>` set correctly
2. Verify the token matches the `WEBHOOK_SECRET` value in the target environment's `.env`
3. Make sure there are no extra spaces or line breaks in the token

#### "Duplicate lead — this email already exists" (409)

**Cause**: A lead with this `corporate_email` is already in the database.

**Fix**: This is expected behavior — the system prevents duplicates. If you need to update the existing lead, use `PUT /api/v1/leads/{id}` instead.

#### "Rate limit exceeded for this API key" (429)

**Cause**: Your API key has exceeded its allowed requests per minute.

**Fix**:
1. Check the `Retry-After` header for when you can try again
2. Ask an admin to increase the key's `rate_limit_per_minute` in the dashboard
3. Set rate limit to `0` for unlimited access

#### Validation errors (422)

**Cause**: Required fields are missing or data is in the wrong format.

**Fix**: Check the `errors` object in the response — it tells you exactly which fields have issues and why.

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "Full Name": ["Full name is required."],
    "Corporate Work Email": ["Corporate email must be a valid email address."]
  }
}
```

#### CORS errors in the browser

**Cause**: The frontend origin isn't in the `CORS_ALLOWED_ORIGINS` environment variable.

**Fix**: Add the frontend's URL to `CORS_ALLOWED_ORIGINS` in the backend's `.env`. Multiple origins are comma-separated. Wildcards are supported (e.g., `https://*.vercel.app`).

#### "No admin user found in database" on Blade dashboard login

**Cause**: The admin user seeder hasn't run.

**Fix**: Run `php artisan db:seed --class=AdminUserSeeder` or set `SEED_ADMIN=true` in the environment and redeploy.
