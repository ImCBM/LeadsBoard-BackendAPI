# B2B Lead Pipeline — Backend Integration Documentation
## n8n Automation → Laravel API | September 2026

## 1. Overview

This document describes the n8n automation and backend ingestion pipeline that collects, cleans, validates, and stores B2B leads. It provides integration specifications for the receiving API endpoints — data shape, field aliasing, normalization rules, deduplication behaviors, and explicit error structures.

## 2. Pipeline Flow

The automation runs in n8n and executes these steps in order for every new lead row:

1. **Trigger** — watches the "ALL_COMBINED_MASTER" tab in the team Google Sheet for newly added rows (polling, checks every 1–2 minutes).
2. **Clean & Tag** — normalizes job titles into tiers (C-Level / VP-Level / Director-Level), applies Title Case to names and locations, formats contact numbers, and tags whether the email is a personal domain (gmail/yahoo/hotmail/outlook).
3. **Upstream Deduplication** — checks the corporate email against previously sent records.
4. **Personal Email Filter** — leads with personal email domains are blocked from reaching the API and logged to the "Exception Log" sheet tab.
5. **Send to API** — leads that pass all checks are sent to the backend endpoint via HTTP POST.
6. **Backend Verification & Ingestion** — backend performs strict deduplication across **both corporate email and contact numbers**, normalizes phone numbers (preserving `+`), tags ingestion source, and returns explicit conflict error details if a duplicate is found.

## 3. Expected Payload & Field Mappings

**Method:** `POST`  
**Endpoint:** `/api/v1/webhook/leads` (or `/api/v1/webhook/leads/bulk` for batch arrays)  
**Content-Type:** `application/json`  
**Authentication:** `Authorization: Bearer <WEBHOOK_SECRET>` or `X-Webhook-Token: <WEBHOOK_SECRET>`

The backend ingestion engine is forgiving and automatically normalizes incoming keys (accepting Title Case, spaced keys, snake_case, and common field variations):

| Field Name (n8n standard) | Accepted Aliases | Type | Required | Notes & Normalization |
|---|---|---|---|---|
| `Full Name` | `full_name`, `name`, `Lead Name` | string | ✅ Yes | Person's full name |
| `Corporate Work Email` | `corporate_email`, `work_email`, `email` | string (email) | ✅ Yes | Corporate email address. **Deduplication key.** Duplicate emails trigger `409 Conflict`. |
| `Company Name` | `company_name`, `company`, `Organization` | string | ✅ Yes | Company name |
| `Contact Number` | `contact_number`, `phone`, `Phone Number`, `Direct Phone`, `mobile`, `telephone` | string | No | Direct contact number. Automatically normalized (spaces, dashes, parens stripped, leading `+` preserved). **Deduplication key.** When provided, duplicate numbers trigger `409 Conflict`. |
| `Job Title` | `job_title`, `title`, `Role` | string | No | Raw title |
| `Title Tier` | `title_tier`, `tier` | string | No | Auto-normalized to `C-Level`, `VP-Level`, `Director-Level`, or `Other` |
| `Email Status` | `email_status`, `email_verification` | string | No | Verification result (e.g., `✅ Valid email`, `verified`) |
| `Clean Root Domain` | `clean_root_domain`, `root_domain`, `domain`, `website` | string | No | Stripped of `https://`, `http://`, `www.`, and trailing slashes |
| `Website Status` | `website_status` | string | No | HTTP status (e.g., `HTTP 200 OK`) |
| `Executive LinkedIn URL` | `executive_linkedin_url`, `linkedin_url`, `personal_linkedin` | string (URL) | No | Executive profile URL |
| `Company LinkedIn Page` | `company_linkedin_page`, `company_linkedin` | string (URL) | No | Company LinkedIn page URL |
| `Industry Classification` | `industry_classification`, `industry` | string | No | Industry classification category |
| `Employee Headcount` | `employee_headcount`, `headcount`, `employees`, `company_size` | integer/string | No | Numeric headcount (non-digits stripped) |
| `HQ Location` | `hq_location`, `location`, `headquarters` | string | No | Full location string (e.g. `Aabenraa, Southern Denmark, Denmark`) |
| `Country` | `country` | string | No | HQ country name (auto-extracted from `hq_location` if omitted) |
| `Tags` | `tags`, `tag_names` | string/array | No | Comma-separated tag names or string array |

### Sample JSON Payload (Single Lead)

```json
{
  "Full Name": "Frank Mortensen",
  "Job Title": "Deputy CEO",
  "Title Tier": "C-Level",
  "Corporate Work Email": "frank@al-sydbank.dk",
  "Contact Number": "+45 74 37 37 37",
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

### Sample Bulk JSON Payload

```json
{
  "leads": [
    {
      "Full Name": "Frank Mortensen",
      "Corporate Work Email": "frank@al-sydbank.dk",
      "Contact Number": "+4574373737",
      "Company Name": "AL Sydbank"
    },
    {
      "Full Name": "Sara Jensen",
      "Corporate Work Email": "sara@nordictech.io",
      "Contact Number": "+4588991122",
      "Company Name": "Nordic Tech"
    }
  ]
}
```

## 4. Deduplication & Conflict Response Specifications

The backend checks for duplicates on **both corporate email and contact number**. To ensure automated pipelines and developers never have to guess why a record was rejected, the API returns itemized, field-specific details.

### Single Lead Collision (HTTP 409 Conflict)

#### 1. Corporate Email Duplicate:
```json
{
  "status": "conflict",
  "error": "Duplicate lead detected: Email frank@al-sydbank.dk already exists in the database.",
  "duplicate_field": "corporate_email",
  "duplicate_fields": [
    "corporate_email"
  ]
}
```

#### 2. Contact Number Duplicate:
```json
{
  "status": "conflict",
  "error": "Duplicate lead detected: Contact number +4574373737 already exists in the database.",
  "duplicate_field": "contact_number",
  "duplicate_fields": [
    "contact_number"
  ]
}
```

#### 3. Both Email and Contact Number Collide:
```json
{
  "status": "conflict",
  "error": "Duplicate lead detected: Email frank@al-sydbank.dk and contact number +4574373737 already exist in the database.",
  "duplicate_field": "multiple",
  "duplicate_fields": [
    "corporate_email",
    "contact_number"
  ]
}
```

### Bulk Ingestion Response (HTTP 200/207)

```json
{
  "message": "Bulk ingestion complete: 1 imported, 1 duplicates skipped, 0 errors.",
  "total": 2,
  "inserted": 1,
  "duplicates": 1,
  "errors": 0,
  "duplicates_detail": [
    {
      "email": "frank@al-sydbank.dk",
      "contact_number": "+4574373737",
      "duplicate_field": "contact_number",
      "duplicate_fields": [
        "contact_number"
      ],
      "reason": "Duplicate lead detected: Contact number +4574373737 already exists in the database."
    }
  ],
  "errors_detail": []
}
```

## 5. Success Response (`201 Created`)

```json
{
  "message": "Lead created successfully.",
  "data": {
    "id": 142,
    "full_name": "Frank Mortensen",
    "corporate_email": "frank@al-sydbank.dk",
    "contact_number": "+4574373737",
    "company_name": "AL Sydbank",
    "clean_root_domain": "al-sydbank.dk",
    "ingestion_channel": "n8n",
    "status": "new",
    "created_at": "2026-09-10T15:20:00.000000Z"
  }
}
```

## 6. Authentication Details

- **Header:** `Authorization: Bearer <token>` or `X-Webhook-Token: <token>`
- Token configured via `WEBHOOK_SECRET` environment variable on the server.
- Staging / Production: Ensure your n8n HTTP Request node provides this header. Missing or invalid tokens will receive `401 Unauthorized`.
