# Testing & CI/CD

> **Audience**: Developers writing or running tests, and project stakeholders wanting to understand the quality assurance process.

---

## Overview

The backend uses **PHPUnit** as its test framework, organized into two suites:

| Suite | Location | What It Tests | Database |
|---|---|---|---|
| **Unit** | `tests/Unit/` | Individual services and models in isolation | In-memory SQLite |
| **Feature** | `tests/Feature/` | Full HTTP request/response cycles through routes and middleware | In-memory SQLite |

All tests use `RefreshDatabase` — each test gets a completely clean, migrated database. This means tests are fully isolated from each other.

---

## Running Tests

### Locally

```bash
# Run all tests
php artisan test

# Run only Unit tests
php artisan test --testsuite=Unit

# Run only Feature tests
php artisan test --testsuite=Feature

# Run a specific test file
php artisan test --filter=WebhookIngestionTest

# Run a specific test method
php artisan test --filter=test_accepts_and_ingests_lead_with_valid_bearer_token

# Run with verbose output
php artisan test -v
```

### Test Configuration

Tests use a dedicated configuration in `phpunit.xml`:

| Setting | Value | Why |
|---|---|---|
| `APP_ENV` | `testing` | Prevents accidental production database access |
| `DB_CONNECTION` | `sqlite` | Fast, no external database needed |
| `DB_DATABASE` | `:memory:` | Ephemeral — destroyed after each test |
| `BCRYPT_ROUNDS` | `4` | Faster password hashing in tests |
| `CACHE_STORE` | `array` | In-memory cache, no file system side effects |
| `QUEUE_CONNECTION` | `sync` | Jobs run immediately during tests |
| `SESSION_DRIVER` | `array` | No session file side effects |

---

## CI/CD Pipeline

Tests run automatically via **GitHub Actions** on every push and pull request.

### When Tests Run

| Trigger | Branches |
|---|---|
| Push | `main`, `master`, `feature/*`, `refactor/*`, `feature/**`, `refactor/**` |
| Pull Request | `main`, `master` |

### CI Environment

The CI pipeline (`.github/workflows/tests.yml`) sets up:

1. **PHP 8.2** with extensions: mbstring, xml, pdo_mysql, pdo_sqlite, bcmath
2. **MySQL 8.0** service container (for production-like database testing)
3. Composer dependency installation (with caching)
4. Application key generation
5. Database migration against MySQL
6. Full test suite execution with `php artisan test`

> **Note**: Locally, tests use in-memory SQLite for speed. In CI, tests run against MySQL to catch any database-engine-specific issues.

---

## Test Files & What They Cover

### Feature Tests (HTTP Integration)

#### `WebhookIngestionTest.php`
**Tests the n8n webhook endpoints end-to-end.**

| Test | What It Validates |
|---|---|
| `test_rejects_webhook_request_without_token` | Missing auth → 401 |
| `test_rejects_webhook_request_with_invalid_token` | Wrong token → 401 |
| `test_accepts_and_ingests_lead_with_valid_bearer_token` | Full lead creation flow with all fields, verifies lead + company + country records |
| `test_rejects_duplicate_corporate_email` | Second lead with same email → 409 Conflict |
| `test_validates_required_fields_in_single_webhook` | Missing required fields → 422 with specific error messages |
| `test_bulk_lead_ingestion` | Bulk endpoint creates multiple leads, verifies country extraction |
| `test_bulk_webhook_validation_errors` | Empty payload, empty array, missing item fields → 422 |

#### `LeadsApiTest.php`
**Tests the REST CRUD API for leads (Sanctum-protected).**

| What It Covers |
|---|
| Listing leads with pagination |
| Filtering by industry, title tier, status, country, search, tags |
| Sorting by various columns |
| Creating leads via API (channel = "api") |
| Updating lead fields (name, email, status, company info, tags) |
| Deleting individual leads |
| CSV export |
| Filter options endpoint |

#### `BulkDeleteAndTagsTest.php`
**Tests bulk delete and bulk tag operations.**

| What It Covers |
|---|
| Bulk delete by IDs |
| Bulk delete by emails |
| Bulk delete by tag |
| Bulk delete by channel |
| Bulk delete by status |
| Bulk delete by date range |
| Validation: must provide at least one selector |
| Bulk tag: add tags to leads by ID |
| Bulk tag: remove tags from leads |
| Bulk tag: sync (replace) tags |
| Bulk tag: target by email |
| Bulk tag: target by filter_tag |

#### `AuthTest.php`
**Tests authentication flow.**

| Test | What It Validates |
|---|---|
| Login with valid credentials → token returned |
| Login with invalid credentials → 401 |
| Logout revokes token |
| `/auth/me` returns current user |
| Protected routes reject unauthenticated requests |

#### `ApiKeyAuthTest.php`
**Tests the API key middleware and rate limiting.**

| Test | What It Validates |
|---|---|
| Valid API key grants access to external routes |
| Invalid key → 401 |
| Deactivated key → 401 |
| Expired key → 401 |
| Rate limiting enforced per key |
| Rate limit = 0 means unlimited |
| Key accepted via `Authorization: Bearer`, `X-API-Key`, and `?api_key=` |

#### `DashboardStatsTest.php`
**Tests the stats/analytics endpoints.**

| What It Covers |
|---|
| Summary endpoint returns correct counts |
| By-industry grouping |
| By-title-tier grouping |
| By-status grouping |
| By-country grouping |
| Timeline endpoint with `days` parameter |

#### `DashboardWebTest.php`
**Tests the Blade (server-rendered) dashboard.**

| What It Covers |
|---|
| Login page renders |
| Dashboard requires authentication |
| Dashboard renders with leads data |
| CSV export from dashboard |

#### `SimulateN8nCommandTest.php`
**Tests the `php artisan n8n:simulate` CLI command.**

| What It Covers |
|---|
| Command executes successfully |
| Sends leads to correct endpoints |
| Handles various payload formats |
| Single lead and bulk modes |
| CSV import mode |

### Unit Tests (Isolated Logic)

#### `LeadIngestionServiceTest.php`
**Tests the ingestion service in isolation — no HTTP layer.**

| Test | What It Validates |
|---|---|
| Field mapping: n8n-style → snake_case conversion |
| Normalization: email lowercasing, domain cleaning, headcount parsing |
| Title tier normalization: `"clevel"` → `"C-Level"`, `"vice president"` → `"VP-Level"` |
| Duplicate detection by email |
| Company deduplication by domain, then by name |
| Company enrichment (adds missing fields to existing company) |
| Country extraction from HQ Location string |
| Location parsing into city / state_region / country |
| Tag auto-creation |
| Bulk ingestion: counts inserted / duplicates / errors correctly |
| Handles edge cases: empty strings, null values, invalid URLs |

#### `ModelsTest.php`
**Tests Eloquent model relationships and accessors.**

| What It Covers |
|---|
| Lead → Company relationship |
| Lead → Tags relationship (many-to-many) |
| Company → Industry, Location relationships |
| Location → Country relationship |
| Lead accessors (company_name, country, tag_names, etc.) |
| Tag `findOrCreateByName` behavior |
| Tag slug auto-generation |
| API Key `isValid()` method (active + not expired) |

---

## Test Patterns & Conventions

### Authentication in Tests

```php
// For webhook-protected routes
$this->withHeaders([
    'Authorization' => 'Bearer test-webhook-secret',
])->postJson('/api/v1/webhook/leads', $payload);

// For Sanctum-protected routes
$user = User::factory()->create();
$this->actingAs($user)->getJson('/api/v1/leads');

// For API key-protected routes
// Create an ApiKey, then use the raw key as Bearer token
$key = ApiKey::create(['key' => hash('sha256', 'test-key'), ...]);
$this->withHeaders(['Authorization' => 'Bearer test-key'])
     ->getJson('/api/v1/external/leads');
```

### Webhook Secret in Tests

Tests set the webhook secret in `setUp()`:

```php
protected function setUp(): void
{
    parent::setUp();
    config(['services.webhook.secret' => 'test-webhook-secret']);
}
```

### Database Assertions

```php
// Verify record exists
$this->assertDatabaseHas('leads', ['corporate_email' => 'frank@example.com']);

// Verify record does NOT exist
$this->assertDatabaseMissing('leads', ['corporate_email' => 'deleted@example.com']);

// Verify count
$this->assertDatabaseCount('leads', 5);
```

---

## Adding New Tests

### Create a Feature Test

```bash
php artisan make:test MyNewFeatureTest
```

This creates `tests/Feature/MyNewFeatureTest.php`. Feature tests should:
- Use `RefreshDatabase` trait
- Make HTTP requests via `$this->getJson()`, `$this->postJson()`, etc.
- Assert HTTP status codes and response JSON

### Create a Unit Test

```bash
php artisan make:test MyServiceTest --unit
```

This creates `tests/Unit/MyServiceTest.php`. Unit tests should:
- Use `RefreshDatabase` trait (if accessing database)
- Instantiate services directly (not via HTTP)
- Test business logic in isolation

---

## Test Coverage Summary

| Area | Tested? | Test File(s) |
|---|---|---|
| Webhook authentication | ✅ | `WebhookIngestionTest` |
| Webhook single lead ingestion | ✅ | `WebhookIngestionTest` |
| Webhook bulk lead ingestion | ✅ | `WebhookIngestionTest` |
| Duplicate email prevention | ✅ | `WebhookIngestionTest`, `LeadIngestionServiceTest` |
| Field validation (422 errors) | ✅ | `WebhookIngestionTest`, `LeadsApiTest` |
| Sanctum auth (login/logout/me) | ✅ | `AuthTest` |
| Lead CRUD (list/create/update/delete) | ✅ | `LeadsApiTest` |
| Lead filtering & search | ✅ | `LeadsApiTest` |
| Lead sorting | ✅ | `LeadsApiTest` |
| CSV export | ✅ | `LeadsApiTest`, `DashboardWebTest` |
| Bulk delete | ✅ | `BulkDeleteAndTagsTest` |
| Bulk tagging | ✅ | `BulkDeleteAndTagsTest` |
| API key auth + rate limiting | ✅ | `ApiKeyAuthTest` |
| Dashboard stats endpoints | ✅ | `DashboardStatsTest` |
| Blade dashboard web UI | ✅ | `DashboardWebTest` |
| n8n simulator command | ✅ | `SimulateN8nCommandTest` |
| Data normalization (title tier, email, domain) | ✅ | `LeadIngestionServiceTest` |
| Model relationships & accessors | ✅ | `ModelsTest` |
| Tag auto-creation & resolution | ✅ | `LeadIngestionServiceTest`, `ModelsTest` |
| Company deduplication & enrichment | ✅ | `LeadIngestionServiceTest` |
