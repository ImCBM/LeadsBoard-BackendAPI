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
| Bulk delete by explicit IDs and ID ranges (`id_ranges: ["101-199"]`) |
| Bulk delete by emails and mixed domain wildcards (`@domain.com`) |
| Bulk delete by exact email domain (`email_domain` — protects other TLDs) |
| Bulk delete by SQL wildcard pattern (`email_pattern: "%@domain.%"`) |
| Tokenized compound deletion: status + email domain (AND logic) |
| Bulk delete by tag, channel, status, and multi-date ranges (`date_ranges`) |
| Validation: requires at least one selector or `confirm: true` |
| Bulk tag: add tags to leads by ID, ID ranges, email domain, or filter_tag |
| Bulk tag: remove tags from leads |
| Bulk tag: sync (replace) tags |
| CLI commands: `leads:cleanup-test` and `leads:tag` (retroactive tagging) |

#### `AuthTest.php`
**Tests authentication flow.**

| Test | What It Validates |
|---|---|
| Login with valid credentials → token returned |
| Login with invalid credentials → 401 |
| Logout revokes token |
| `/auth/me` returns current user |
| Protected routes reject unauthenticated requests |

#### `NormalizedSchemaTest.php` (23 Tests)
**Verifies 3NF database schema, foreign keys, cascades, and Eloquent relationships.**

| Test Focus | What It Validates |
|---|---|
| Schema Integrity | Existence and columns for `tiers`, `api_scopes`, `tier_scopes`, `tier_field_restrictions`, `subscriptions`, `api_keys`, `api_key_scopes`, `api_traces` |
| Foreign Key Cascades | Deleting a user cascades to subscriptions and API keys |
| Model Relationships | `Tier->scopes()`, `Tier->fieldRestrictions()`, `User->subscriptions()`, `ApiKey->tier()`, `ApiKey->scopes()` |
| Helper Methods | `Tier::hasScope()`, `Tier::isFieldRestricted()`, `User::isInternalDeveloper()`, `User::getTier()` |

#### `SeederTest.php` (6 Tests)
**Tests database seeders, tier attributes, and test persona matrix.**

| Test | What It Validates |
|---|---|
| `default_tiers_are_seeded_with_expected_attributes` | Validates `free`, `pro`, `enterprise`, and `developer` rates, limits, and flags |
| `all_granular_api_scopes_are_seeded` | Verifies all 10 scopes (`leads:*`, `tags:*`, `stats:*`, `webhooks:post`) |
| `tiers_have_correct_scope_assignments` | Validates scope mapping across Free, Pro, Enterprise, and Developer tiers |
| `free_tier_has_field_restrictions_seeded` | Verifies `contact_number` restriction on Free tier; unrestricted on Pro/Dev |
| `test_persona_users_are_seeded_with_correct_subscriptions` | Confirms subscriptions for admin (developer), free, pro, and enterprise users |
| `deterministic_n8n_test_webhook_key_is_seeded` | Verifies `lb_sec_n8n_test_automation_key` is seeded with `developer` tier and `webhooks:post` scope |

#### `ApiKeyServiceTest.php` (6 Tests)
**Tests API key lifecycle, entitlement gating, and quota management.**

| Test | What It Validates |
|---|---|
| Secret key generation | Generates `lb_sec_...` key, persists SHA-256 hash, attaches scopes |
| Public key generation | Generates `lb_pub_...` key, restricts mutation scopes |
| Key-level rate budgeting | Rejects client keys whose budget exceeds tier rate limit ceiling |
| Key quota enforcement | Rejects creation when client active key count reaches tier `max_keys` |
| Developer / Admin bypass | Internal developers can create custom tier keys with wildcard `*` scope |

#### `DualBucketRateLimiterTest.php` (4 Tests)
**Tests account-level aggregate and key-level budget rate limiting.**

| Test | What It Validates |
|---|---|
| Key rate limit | Checks both individual key budget and account aggregate bucket |
| Account exhaustion | Throttles all keys belonging to an account when aggregate 1-minute quota is reached |
| Daily quota | Throttles requests when rolling 24-hour limit is reached |
| Headers | Confirms `X-RateLimit-Limit`, `X-RateLimit-Remaining`, and `X-RateLimit-Reset` |

#### `FieldLevelAuthorizationTest.php` (4 Tests)
**Tests dynamic field masking via `LeadResource.php`.**

| Test | What It Validates |
|---|---|
| Free tier single lead | Masks `contact_number: null` and injects `meta.tier_restricted_fields: ["contact_number"]` |
| Free tier lead list | Masks `contact_number` across all items in paginated listings |
| Pro tier single lead | Returns unmasked `contact_number` |
| Enterprise / Developer | Returns unmasked `contact_number` |

#### `WebhookIngestionAuthTest.php` (5 Tests)
**Tests webhook authentication unification and simulation.**

| Test | What It Validates |
|---|---|
| Deterministic key auth | `lb_sec_n8n_test_automation_key` successfully authenticates webhook ingest |
| Scope requirement | Key lacking `webhooks:post` receives `403 Forbidden` |
| Public key rejection | Public key (`lb_pub_...`) attempting webhook post receives `403 Forbidden` |
| Legacy secret rejected | Static `WEBHOOK_SECRET` without API key is rejected (`401 Unauthorized`) |
| Simulator command | `php artisan n8n:simulate` supports `--api-key` and `--limit` options |

#### `DeveloperApiKeyControllerTest.php` (5 Tests)
**Tests the React Customer Portal REST API (`/api/developer/api-keys`).**

| Test | What It Validates |
|---|---|
| List owned keys | Returns keys with active tier metadata, allowed scopes, and max key counts |
| Create valid key | Creates key within tier ceiling and returns plaintext token once |
| Scope gating | Rejects client request for ungranted scopes (`403 Forbidden`) |
| Quota limit | Returns `422` when max keys limit reached |
| Rotate key | Generates new secret and replaces hash while preserving ID and scopes |

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
