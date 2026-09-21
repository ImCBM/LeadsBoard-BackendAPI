# Lead Cleanup & Bulk Operations Guide

> **Audience**: Developers, n8n Automation Engineers, and Non-Technical Project Stakeholders.  
> **Applicability**: Covers deleting, bulk-cleaning, and retroactively tagging leads with or without the tag system.  
> **Last Updated**: September 2026

---

## Quick Navigation by Role

| Audience | What You Will Learn | Jump To |
|---|---|---|
| **Non-Techs & Managers** | Plain-English summary, safety guarantees, and real-world scenario playbooks | [Non-Tech Guide & Scenarios](#1-non-tech-guide--scenarios) |
| **n8n Automation Team** | How to trigger deletions from n8n workflows without tags, node setup, payloads | [n8n Automation Guide](#2-n8n-automation-guide) |
| **Developers & Ops** | Code architecture, validation rules, database impact, and Artisan CLI commands | [Developer & Ops Guide](#3-developer--ops-guide) |

---

## Core Concept: Deleting Leads With and Without Tags

### Historical Context vs. Today

* **Before the Tag System (Prior to commit `a99d52a`)**:  
  Bulk deletion **did not exist in the API**. Deletions could only occur one lead at a time via `DELETE /api/v1/leads/{id}` or via direct SQL queries on the database.
* **Today (Current System)**:  
  A centralized `LeadBulkService` handles bulk deletions across **both webhook and authenticated API routes**. While tagging is supported, **tags are completely optional** for deletion. You can select leads by IDs, corporate emails, channel, status, date range, or explicit full wipe.

### What Actually Happens When a Lead is Deleted?

```
┌─────────────────────────────────────────────────────────────┐
│                       LEAD DELETION                         │
├─────────────────────────────────────────────────────────────┤
│  1. Lead Record            ──▶  PERMANENTLY DELETED         │
│  2. Lead Tag Pivot Links   ──▶  PERMANENTLY DELETED         │
│  3. Company Record         ──▶  PRESERVED (retained in DB)  │
│  4. Industry & Location    ──▶  PRESERVED (retained in DB)  │
└─────────────────────────────────────────────────────────────┘
```

1. **The Lead is permanently removed** (`leads` table).
2. **Tag associations are cleaned up** (`lead_tag` pivot table).
3. **Company, Location, and Industry records remain untouched**: Other leads from the same company will still reference that company. Retaining the company also preserves domain deduplication records.
4. **Safety & Atomicity**: Deletions execute within database transactions in chunks of 500 to prevent database table lockups during high volume.

---

## 1. Non-Tech Guide & Scenarios

If you are a project manager, QA tester, or business stakeholder, this section explains how cleanup works in plain terms.

### Safety Guarantees

* **You cannot accidentally delete everything with an empty command**: If an automation or user sends a blank delete request, the system rejects it (`422 Validation Error`). A complete wipe requires an explicit `confirm: true` flag.
* **Deletions are permanent**: There is no "recycle bin". Once deleted, the lead must be re-ingested from n8n or scraped again.
* **Company profiles are safe**: Deleting leads does not break or delete company profiles.

### Playbook: Common Situations

#### Scenario A: "We scraped test leads before the tag system was added and want to remove them."
* **Solution**: You can delete them by **Date Range** (all leads created before the launch date) or by **Ingestion Channel** (e.g. all leads from the `n8n` channel).
* **Alternative**: If you want to keep them but label them first, ask a developer to run `php artisan leads:tag --tag=legacy` to label them before making a final decision.

#### Scenario B: "A lead or company requested GDPR deletion / removal of their email."
* **Solution**: You can remove specific leads by their exact work email addresses without knowing their database IDs or tags.

#### Scenario C: "We want to clean up bad or rejected leads from last month."
* **Solution**: Target by status `rejected` combined with a date range (e.g., `date_from: 2026-08-01`, `date_to: 2026-08-31`).

#### Scenario D: "We want to reset our staging / pre-prod database to zero."
* **Solution**: Send a bulk delete with `confirm: true` to the staging server.

---

## 2. n8n Automation Guide

For n8n workflow creators, external automation engineers, and webhook integrations.

### Endpoint & Authentication

* **Method**: `POST`
* **URL**: `https://<YOUR-API-HOST>/api/v1/webhook/leads/bulk-delete`
* **Header**: `X-Webhook-Token: <YOUR_WEBHOOK_SECRET>`  
  *(or `Authorization: Bearer <YOUR_WEBHOOK_SECRET>`)*

### Selector Options (No Tags Required)

You must provide **at least one** selector in your JSON payload:

| Field Name | Type | Description | Example |
|---|---|---|---|
| `lead_ids` or `ids` | Array | Specific IDs or range strings | `[12, 15, "101-199"]` |
| `id_ranges` | Array of strings | Multi-range ID selectors | `["101-199", "250-255"]` |
| `id_from` / `id_to` | Integer | Min and max ID bounds | `"id_from": 100, "id_to": 200` |
| `email_domain` | String | Target exact email domain (auto-protects `.net`, etc.) | `"demodomain.com"` or `"@demodomain.com"` |
| `email_domains` | Array of strings | Target multiple email domains | `["demodomain.com", "partner.dk"]` |
| `email_pattern` | String | SQL wildcard pattern matching across email | `"%@demodomain.%"` or `"%@test%"` |
| `emails` | Array of strings | Specific emails OR domain wildcards (prefix with `@`) | `["lead@target.com", "@demodomain.com"]` |
| `channel` or `ingestion_channel` | String | Source channel (`n8n`, `api`, `csv_import`, `manual`) | `"n8n"` |
| `status` | String | Status (`new`, `reviewed`, `qualified`, `rejected`) | `"rejected"` |
| `date_from` / `date_to` | String (Y-m-d) | Single continuous creation date window | `"date_from": "2026-08-01"` |
| `date_ranges` | Array | Multiple discrete date windows | `[{"from": "2026-01-01", "to": "2026-01-15"}]` |
| `confirm` | Boolean | Required `true` if no selector is given (deletes all) | `true` |

> [!NOTE]
> **Tokenized / Compound Filtering**: You can combine any of these selectors! The backend connects all provided criteria with SQL `AND`. Only leads matching **every** condition will be targeted.

---

### Copy-Paste n8n Payloads

#### 1. Wide Email Domain Deletions (Exact Domain vs. Broad Pattern)

##### Option A: Exact Domain Match (Protects Other TLDs)
Deletes all leads with `@demodomain.com`, but **protects** `@demodomain.net` and `@demodomain.dk`:
```json
{
  "email_domain": "demodomain.com"
}
```

##### Option B: Brand Wildcard Across All TLDs
Matches `@demodomain.com`, `@demodomain.net`, and `@demodomain.org`:
```json
{
  "email_pattern": "%@demodomain.%"
}
```

##### Option C: Mixed Array (Specific Emails + Domain Wildcards)
You can include full emails alongside `@domain` wildcards in the standard `emails` array:
```json
{
  "emails": [
    "specific_person@samplecorp.dk",
    "@demodomain.com",
    "@testleads.org"
  ]
}
```

---

#### 2. Tokenized Compound Deletions ("AND" Filtering)

Target only leads meeting **all** specified criteria at the same time:

##### Example: Only Rejected leads under `@demodomain.com`
Leaves `new` or `qualified` leads from `@demodomain.com` untouched, and leaves rejected leads from other companies untouched:
```json
{
  "status": "rejected",
  "email_domain": "demodomain.com"
}
```

##### Example: Only leads from `n8n` channel, rejected, within a specific ID window
```json
{
  "channel": "n8n",
  "status": "rejected",
  "id_ranges": ["1000-1500"]
}
```

---

#### 3. Multi-Range ID Deletions

Delete multiple disjoint ID blocks in a single request:
```json
{
  "id_ranges": [
    "101-199",
    "250-255",
    "500-600"
  ]
}
```
Or mix single IDs with ranges in `lead_ids`:
```json
{
  "lead_ids": [
    12,
    15,
    "101-199",
    250
  ]
}
```

---

#### 4. Multi-Date Range Deletions

Target specific non-contiguous campaign date windows:
```json
{
  "date_ranges": [
    { "from": "2026-01-01", "to": "2026-01-15" },
    { "from": "2026-03-01", "to": "2026-03-15" }
  ]
}
```
*(Also accepts string syntax: `["2026-01-01..2026-01-15", "2026-03-01..2026-03-15"]`)*

---

#### 5. Delete Leads Created Before Tags Existed (Cutoff Date)
```json
{
  "date_to": "2026-08-20"
}
```

---

#### 6. Complete Database Wipe (Caution!)
```json
{
  "confirm": true
}
```

---

### Step-by-Step: n8n HTTP Request Node Configuration

1. Add an **HTTP Request** node to your n8n workflow.
2. Set **Method** to `POST`.
3. Set **URL** to:
   * Staging (Render): `https://leadsboard-backendapi.onrender.com/api/v1/webhook/leads/bulk-delete`
   * Production (Hostinger): `https://b2bleadscraper.certicode.net/api/v1/webhook/leads/bulk-delete`
4. Under **Headers**, add:
   * Name: `X-Webhook-Token`
   * Value: `{{ $env.WEBHOOK_SECRET }}` (or paste the static token)
5. Under **Send Body**, toggle to `JSON`.
6. Enter your JSON selector (e.g. `{"channel": "n8n", "date_to": "2026-08-20"}`).

### Expected Success Response (`200 OK`)

```json
{
  "message": "Bulk delete complete: 24 leads deleted.",
  "deleted_count": 24,
  "deleted_ids": [12, 13, 14, 15, 16],
  "criteria": {
    "channel": "n8n",
    "date_to": "2026-08-20"
  }
}
```

---

## 3. Developer & Ops Guide

For backend engineers, DevOps, and administrators with terminal or API access.

### Available Routes

| Route | Method | Auth | Description |
|---|---|---|---|
| `/api/v1/webhook/leads/bulk-delete` | `POST` | Webhook Token | For n8n and automation scripts |
| `/api/v1/leads/bulk-delete` | `POST` | Sanctum Bearer Token | For frontend dashboard and authenticated users |
| `/api/v1/leads/bulk` | `DELETE` | Sanctum Bearer Token | RESTful alias for bulk delete |
| `/api/v1/leads/{id}` | `DELETE` | Sanctum Bearer Token | Delete a single lead by ID |

---

### CLI Tools (Artisan Commands)

You can perform safe, interactive cleanups directly from the server shell without sending HTTP requests.

#### A. Cleanup Test Leads (`leads:cleanup-test`)

Located in [`app/Console/Commands/CleanupTestLeadsCommand.php`](file:///c:/Works/CertiCodeThings/LeadsBoard/LeadsBoard-BackendAPI/app/Console/Commands/CleanupTestLeadsCommand.php).

* **Preview matching leads without deleting (`--dry-run`)**:
  ```bash
  php artisan leads:cleanup-test --channel=n8n --company-pattern="%Test%" --dry-run
  ```
* **Run interactive deletion (prompts for confirmation)**:
  ```bash
  php artisan leads:cleanup-test --channel=n8n --company-pattern="%Test%"
  ```
* **Force deletion in CI/CD or automated maintenance**:
  ```bash
  php artisan leads:cleanup-test --channel=n8n --force
  ```

#### B. Retroactively Tag Older / Legacy Leads (`leads:tag`)

Located in [`app/Console/Commands/RetroactivelyTagLeadsCommand.php`](file:///c:/Works/CertiCodeThings/LeadsBoard/LeadsBoard-BackendAPI/app/Console/Commands/RetroactivelyTagLeadsCommand.php).

If you have untagged leads from before the tag system was built, you can **backfill tags** across them first instead of deleting blindly:

* **Preview matching leads**:
  ```bash
  php artisan leads:tag --tag=legacy-import --channel=n8n --dry-run
  ```
* **Tag all n8n leads as "legacy-import"**:
  ```bash
  php artisan leads:tag --tag=legacy-import --channel=n8n
  ```
* **Tag leads by company name pattern**:
  ```bash
  php artisan leads:tag --tag=test --company-pattern="%Demo%"
  ```
* **Tag specific lead IDs**:
  ```bash
  php artisan leads:tag --tag=reviewed-q3 --ids=1,2,3,4,5
  ```

---

### Internal Architecture

The query building and chunked transaction logic is centralized in [`app/Services/LeadBulkService.php`](file:///c:/Works/CertiCodeThings/LeadsBoard/LeadsBoard-BackendAPI/app/Services/LeadBulkService.php). Both `bulkDelete` and `bulkTag` share the exact same `buildTargetQuery` method:

```php
public function buildTargetQuery(array $criteria): Builder
{
    $query = Lead::query();

    // 1. Explicit IDs, id_ranges (e.g. "101-199"), and id_from/id_to
    $this->applyIdCriteria($query, $criteria);

    // 2. Exact Emails, Email Domains (@domain.com), and SQL Wildcards (%pattern%)
    $this->applyEmailCriteria($query, $criteria);

    // 3. Tags / Filter Tags
    if (!empty($criteria['tag'] ?? $criteria['filter_tag'])) {
        $query->byTag($criteria['tag'] ?? $criteria['filter_tag']);
    }

    // 4. Ingestion Channel
    if (!empty($criteria['channel'] ?? $criteria['ingestion_channel'])) {
        $query->byChannel($criteria['channel'] ?? $criteria['ingestion_channel']);
    }

    // 5. Status
    if (!empty($criteria['status'])) {
        $query->byStatus($criteria['status']);
    }

    // 6. Date Ranges (single date_from/date_to and multi-window date_ranges)
    $this->applyDateCriteria($query, $criteria);

    return $query;
}
```

#### Chunked Transaction Safety
Once matching IDs are retrieved, deletions are executed in batches of 500:

```php
DB::transaction(function () use ($deletedIds) {
    foreach (array_chunk($deletedIds, 500) as $chunk) {
        DB::table('lead_tag')->whereIn('lead_id', $chunk)->delete();
        Lead::whereIn('id', $chunk)->delete();
    }
});
```

---

## 4. Troubleshooting & Error Codes

| Code | Error Message | Cause | How to Fix |
|---|---|---|---|
| `401` | `Unauthorized` | Missing or incorrect Webhook Secret or Sanctum Bearer Token | Check `X-Webhook-Token` matches `WEBHOOK_SECRET` in `.env` |
| `422` | `You must specify at least one deletion selector...` | The request body was empty `{}` and `confirm: true` was not sent | Add a selector (`channel`, `date_to`, `emails`, `email_domain`, `id_ranges`, `lead_ids`) or pass `"confirm": true` |
| `422` | `The id_ranges must be an array of range strings` | Format error in `id_ranges` | Ensure ranges are strings in format `"101-199"`, e.g. `["101-199"]` |
| `200` | `Bulk delete complete: 0 leads deleted` | The request was valid, but no database records matched the given criteria | Verify date format (`YYYY-MM-DD`), channel name, domain spelling, or email casing |

