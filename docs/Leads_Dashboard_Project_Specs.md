# Leads Dashboard
## Project Specification

## 1. Overview

This document covers the Leads Dashboard project, as discussed in the meeting: a web-based system for ingesting, cleaning, storing, filtering, and exporting enriched leads. The project is intended to streamline internal workflows, improve traceability, and reduce manual work.

## 2. Objective

Build a dashboard that manages enriched leads coming from the data mining process. The system should ingest data from a master sheet, store it in MySQL, and provide filtering, search, monitoring, and CSV export.

## 3. Workflow

- Data mining team places lead data into a master sheet.
- System sends data from Google Sheet to MySQL via REST API, webhook, or pipeline.
- Data is validated, normalized, and filtered.
- Records are displayed in a web dashboard.
- Users can search, filter, count, and export data.

## 4. Core Features

- Lead table/dashboard view
- Filters by: industry/category, position, date added
- Search
- Summary counts: total saved, successful inserts, missing/null records, records per day/week/month
- CSV download
- Login and admin account
- Error handling and validation
- Duplicate prevention for: email, contact number

## 5. Data Rules

- Normalize data before saving
- Map null or blank values safely
- Reject duplicate unique fields
- Ensure compatibility with MySQL data types

## 6. Technical Notes

- Source: Google Sheets / master sheet
- Transfer method: REST API / webhook
- Storage: MySQL
- UI: dashboard with data table component

## 7. Expected Output

A simple, usable dashboard for monitoring enriched leads and exporting them for downstream use.

## 8. Project Scope Summary

A data ingestion and monitoring dashboard for lead records with search, filtering, summary counts, and CSV export.

## 9. MVP Summary

- Import from master sheet
- Save to MySQL
- Validate and normalize data
- Prevent duplicates
- Dashboard with filters/search
- CSV export
- Login/admin access

## 10. Development Breakdown

### Backend
- MySQL schema
- REST API ingestion
- Validation and duplicate checks
- Summary/count endpoints
- CSV export

### Frontend
- Login page
- Dashboard table
- Filters and search
- Summary cards
- CSV download

### QA
- Duplicate prevention testing
- Null handling testing
- Export and filter testing

## 11. Conclusion

The Leads Dashboard handles data enrichment and lead monitoring, focusing on automation, traceability, and reducing manual work.
