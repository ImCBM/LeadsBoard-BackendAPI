# B2B Lead Pipeline — Backend Integration Documentation
## n8n Automation → Laravel API | August 19, 2026

## 1. Overview

This document describes the n8n automation that collects, cleans, and validates B2B leads before sending them to the backend. It's meant to give you (backend dev) everything needed to build the receiving API endpoint — what data arrives, in what shape, how often, and what's already been handled upstream so you don't duplicate that logic.

## 2. Pipeline Flow

The automation runs in n8n and executes these steps in order for every new lead row:

1. **Trigger** — watches the "ALL_COMBINED_MASTER" tab in the team Google Sheet for newly added rows (polling, checks every 1–2 minutes).
2. **Clean & Tag** — normalizes job titles into tiers (C-Level / VP-Level / Director-Level), applies Title Case to names and locations, and tags whether the email is a personal domain (gmail/yahoo/hotmail/outlook).
3. **Deduplication** — checks the corporate email against every email previously sent through this pipeline. If it's already been sent before, the lead is dropped here and never reaches your API.
4. **Personal Email Filter** — leads with a personal email domain are blocked from reaching your API and are instead logged to a separate "Exception Log" sheet tab for audit purposes.
5. **Send to API** — leads that pass all checks are sent to the backend endpoint as an HTTP POST request with a JSON body (see Section 3).

## 3. Expected Payload

**Method:** POST
**Content-Type:** application/json

Below are the fields currently sent, based on the data available upstream. Field names are provisional — happy to rename anything to match your database schema once confirmed.

| Field Name | Type | Example Value | Notes |
|---|---|---|---|
| Full Name | string | Frank Mortensen | Title Case applied |
| Job Title | string | Deputy CEO | Raw title, unmodified |
| Title Tier | string | C-Level | One of: C-Level, VP-Level, Director-Level, Other |
| Corporate Work Email | string | info@sydbank.dk | Personal domains (gmail/yahoo/hotmail/outlook) already filtered out before reaching this endpoint |
| Email Status | string | ✅ Valid email | From upstream data source |
| Company Name | string | AL Sydbank | |
| Clean Root Domain | string | al-sydbank.dk | Already stripped of https/www/subpaths upstream |
| Website Status | string | HTTP 200 OK | |
| Executive LinkedIn URL | string (URL) | https://linkedin.com/in/... | |
| Company LinkedIn Page | string (URL) | https://linkedin.com/company/... | Can be empty string |
| Industry Classification | string | Banking / Financial Services | |
| Employee Headcount | integer or empty | 53 | Can be blank/empty string if unknown |
| HQ Location | string | Aabenraa, Southern Denmark, Denmark | Title Case applied |

### Sample JSON payload

```json
{
  "Full Name": "Frank Mortensen",
  "Job Title": "Deputy CEO",
  "Title Tier": "C-Level",
  "Corporate Work Email": "info@sydbank.dk",
  "Email Status": "✅ Valid email",
  "Company Name": "AL Sydbank",
  "Clean Root Domain": "al-sydbank.dk",
  "Website Status": "HTTP 200 OK",
  "Executive LinkedIn URL": "https://www.linkedin.com/in/frank-mortensen62b972/",
  "Company LinkedIn Page": "https://www.linkedin.com/company/al-sydbank/",
  "Industry Classification": "Banking / Financial Services",
  "Employee Headcount": "",
  "HQ Location": "Aabenraa, Southern Denmark, Denmark"
}
```

## 4. What's Already Handled Upstream (n8n side)

- **Personal email blocking** — gmail/yahoo/hotmail/outlook addresses never reach your API.
- **Duplicate email prevention** — the same corporate email will not be sent twice.
- **Job title normalization** — mapped into tiers (C-Level, VP-Level, Director-Level, Other).
- **Name/location casing** — formatted into Title Case.
- **Domain cleanup** — root domains arrive already stripped of protocol, www, and subpaths.

## 5. What We Still Need From You

To finish wiring this up on our end, we need answers to the following:

- The real endpoint URL (currently pointed at a placeholder/mock URL for testing).
- Authentication requirements — API key, bearer token, or none? Where should it go (header/body)?
- Expected success response (status code + body) so we can confirm delivery.
- Expected error/failure response format, so our alerting can detect and report failures correctly.
- Whether you'll also deduplicate on company domain (Clean Root Domain) on your end, since we're currently only deduplicating by email.
- Confirmation that the field names above match your database schema, or a list of renames needed.

## 6. Current Status

The n8n workflow is fully built and tested end-to-end using a mock endpoint (webhook.site) in place of the real API. Once the above details are confirmed, switching to the live endpoint is a one-line URL change — no rebuild needed on our side.
