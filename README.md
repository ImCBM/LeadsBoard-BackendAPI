# LeadsBoard — B2B Lead Pipeline Backend

LeadsBoard is a Laravel 12 based backend service designed to act as the central hub for your B2B lead generation pipeline. It receives lead data from external automation tools (like n8n), validates and deduplicates the data, stores it securely, and provides both a web dashboard and a robust REST API for managing the leads.

## Features

- **Webhook Ingestion:** Secure `POST` endpoint for n8n to send in new leads.
- **Deduplication & Validation:** Automatically drops duplicate leads (by corporate email) and normalizes names, titles, and locations.
- **Web Dashboard:** A fast, responsive Blade-based UI featuring "Fresh Minimalism" design aesthetics.
- **Advanced Filtering & Sorting:** Combine multiple filters (Industry, Tier, Status, Country) with full-text search and A-Z/Z-A sorting.
- **API Key Management:** Visual UI to generate secure API keys with granular rate limiting (`429 Too Many Requests` enforcement).
- **Data Export:** Streamed CSV exports with UTF-8 BOM for full Microsoft Excel compatibility.

## Requirements

- PHP 8.2+
- Composer
- MySQL (or SQLite for local development)
- Node.js & NPM (for Vite assets)

## Installation & Setup

1. **Clone the repository:**
   ```bash
   git clone <your-repo-url>
   cd JobBoard-Laravel
   ```

2. **Install dependencies:**
   ```bash
   composer install
   npm install
   npm run build
   ```

3. **Environment Configuration:**
   Copy the example environment file:
   ```bash
   cp .env.example .env
   ```
   Generate the application key:
   ```bash
   php artisan key:generate
   ```
   **Important Variables in `.env`:**
   - `WEBHOOK_SECRET`: The static bearer token n8n will use to authenticate requests.
   - `API_DEFAULT_RATE_LIMIT`: Default requests per minute for API keys.
   - `DB_CONNECTION`: Set to `sqlite` for local dev or `mysql` for production.

4. **Database Migration & Seeding:**
   ```bash
   php artisan migrate --seed
   ```
   *Note: The seeder creates a default admin user (`admin@leadsboard.local` / `password`), sample leads, and a test API key.*

5. **Run the local server:**
   ```bash
   php artisan serve
   ```
   Access the dashboard at `http://localhost:8000/dashboard`.

## Documentation

Full API documentation, including request payloads, responses, and authentication methods, is available in the `docs/API_DOCUMENTATION.md` file.

## Deployment (Hostinger Shared Hosting)

Detailed deployment instructions for Hostinger (or similar cPanel/hPanel shared hosts) are available in the API Documentation (`docs/API_DOCUMENTATION.md` Section 5). 

Ensure your `.env` is updated with your production MySQL credentials, and your Document Root points to the `public/` directory.
