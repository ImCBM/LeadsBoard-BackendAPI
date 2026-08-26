# LeadsBoard — B2B Lead Pipeline Backend

LeadsBoard is a Laravel 12 based backend service designed to act as the central hub for your B2B lead generation pipeline. It receives lead data from external automation tools (like n8n), validates and deduplicates the data, stores it securely, and provides a robust REST API for managing the leads.

> ⚠️ **Note:** This repository is the **Backend API** only. The React UI is located in the [LeadsBoard-Frontend](../LeadsBoard-Frontend) repository.

---

## 🚀 Features

- **Webhook Ingestion:** Secure `POST` endpoint for n8n to send in new leads.
- **Deduplication & Validation:** Automatically drops duplicate leads (by corporate email) and normalizes names, titles, and locations.
- **Advanced Filtering & Sorting:** Combine multiple filters (Industry, Tier, Status, Country) with full-text search and A-Z/Z-A sorting.
- **API Key Management:** Granular rate limiting (`429 Too Many Requests` enforcement).
- **Data Export:** Streamed CSV exports with UTF-8 BOM for full Microsoft Excel compatibility.

---

## 🛠️ Getting Started

### Prerequisites
- PHP 8.2+
- Composer
- SQLite (local dev) or MySQL (production)

### Installation
1. **Clone & Install:**
   ```bash
   git clone <your-repo-url>
   cd LeadsBoard-BackendAPI
   composer install
   ```

2. **Environment Setup:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   *Ensure `DB_CONNECTION=sqlite` is set for local development.*

3. **Database & Seed:**
   ```bash
   php artisan migrate --seed
   ```
   *(This creates a default admin user `admin@leadsboard.local` / `password` and sample leads)*

4. **Start the Server:**
   ```bash
   php artisan serve
   ```
   The API will be available at `http://127.0.0.1:8000`.

---

## 🔗 Connecting the Frontend

For the full LeadsBoard experience, run the React frontend alongside this API.
1. Leave this backend running on port 8000.
2. In the **LeadsBoard-Frontend** project's `.env` file, set:
   ```env
   VITE_API_BASE_URL=http://127.0.0.1:8000
   ```
3. Start the frontend server (`npm run dev`).

Laravel automatically handles the CORS `OPTIONS` preflight requests for local development.

---

## 🔐 API Authentication & Usage

The API supports three distinct authentication methods depending on the consumer:

### 1. n8n / External Webhooks
For automated tools pushing data *into* LeadsBoard.
- **Authentication:** Static Bearer Token
- **Setup:**
  1. Copy the `WEBHOOK_SECRET` value from your `.env` file.
  2. In n8n, create a **Header Auth** credential.
  3. Set Name to `Authorization` and Value to `Bearer your-secret-here`.
- **Example Usage:**
  ```bash
  curl -X POST http://127.0.0.1:8000/api/v1/webhook/leads \
    -H "Authorization: Bearer local-dev-webhook-secret-change-me" \
    -H "Content-Type: application/json" \
    -d '{"full_name": "Jane Doe", "corporate_email": "jane@example.com"}'
  ```

#### 🧪 Testing Without n8n Access (Webhook Simulator)
If you do not have access to n8n, use the built-in simulator command to replay CSV leads against the webhook endpoint:
```bash
# Preview payloads without sending
php artisan n8n:simulate --dry-run

# Send sample leads to local backend (php artisan serve must be running)
php artisan n8n:simulate

# Bulk send in a single batch POST
php artisan n8n:simulate --bulk

# Send with delay between POSTs to simulate real-time polling cadence
php artisan n8n:simulate --delay=500

# Use a custom CSV
php artisan n8n:simulate --csv=path/to/leads.csv
```

Alternatively, seed directly from CSV without HTTP:
```bash
php artisan db:seed --class=CsvImportSeeder
```


### 2. 3rd-Party Integrations (API Keys)
For external services pulling or managing data via the `/external` routes.
- **Authentication:** Dynamic Bearer Token (Sanctum)
- **Setup:**
  1. Go to the backend dashboard: `http://127.0.0.1:8000/dashboard`
  2. Log in (`admin@leadsboard.local` / `password`).
  3. Click **Generate API Key** and copy the plain-text token.
- **Example Usage:**
  ```bash
  curl -X GET http://127.0.0.1:8000/api/v1/external/leads \
    -H "Authorization: Bearer 1|your_generated_api_key_here" \
    -H "Accept: application/json"
  ```

### 3. The React Frontend
For the internal web application.
- **Authentication:** Auto-managed Sanctum Tokens
- **Setup:** The frontend hits `POST /api/v1/auth/login` and automatically attaches the resulting token to all subsequent requests. No manual setup required.

---

## 🚨 Troubleshooting Local Quirks

- **Requests taking exactly ~500ms?**
  Windows Defender intercepts and scans the 500+ framework files Laravel opens on every request. Add your code folder to the Windows Defender **Exclusions list** to fix this. In a production Linux environment, these requests take 10-30ms.
  
- **Frontend says "Network Error"?**
  Modern Node.js prioritizes IPv6. If the frontend `.env` uses `http://localhost:8000`, Node attempts IPv6 (`[::1]`) while Laravel only listens on IPv4 (`127.0.0.1`). Always use explicit IPv4 in your frontend: `VITE_API_BASE_URL=http://127.0.0.1:8000`.

- **CORS Errors in Production?**
  Ensure your production frontend domain (e.g., `https://leads.yourdomain.com`) is explicitly allowed in Laravel's CORS configuration.

---

## 📚 Documentation & Deployment

- **API Reference:** Detailed payloads and responses are in [`docs/API_DOCUMENTATION.md`](docs/API_DOCUMENTATION.md).
- **Deployment:** Instructions for Hostinger and cPanel shared hosting are available in the API Documentation (Section 5).
