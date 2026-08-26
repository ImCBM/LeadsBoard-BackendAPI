<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Simulates the n8n automation pipeline by reading a CSV file and
 * POSTing each row as a webhook request to the LeadsBoard API.
 *
 * This exercises the exact same code path a real n8n deployment would use:
 * ValidateWebhookToken middleware → StoreLeadRequest validation →
 * LeadIngestionService (normalize, dedup) → database insert.
 *
 * Usage:
 *   php artisan n8n:simulate                       # Default CSV, single-mode
 *   php artisan n8n:simulate --csv=leads.csv       # Custom CSV
 *   php artisan n8n:simulate --bulk                # Bulk endpoint
 *   php artisan n8n:simulate --delay=500           # 500ms between POSTs
 *   php artisan n8n:simulate --dry-run             # Preview payloads only
 *   php artisan n8n:simulate --limit=5             # Only process first 5 rows
 */
class SimulateN8nCommand extends Command
{
    protected $signature = 'n8n:simulate
        {--csv=          : Path to CSV file (default: docs/sample_lead_list.csv)}
        {--bulk          : Send all leads in a single bulk POST instead of one-by-one}
        {--delay=0       : Milliseconds to wait between individual POSTs (simulates polling cadence)}
        {--dry-run       : Show payloads without sending them}
        {--limit=0       : Limit number of leads to process (0 = all)}
        {--base-url=     : Override the API base URL (default: auto-detected from APP_URL)}
        {--port=         : Override the port (default: auto-detected from APP_PORT or 8000)}';

    protected $description = 'Simulate n8n webhook by reading a CSV and POSTing leads to the webhook endpoint';

    /**
     * CSV column → n8n payload key mapping.
     * The CSV headers from the sample match the n8n payload spec exactly.
     */
    private const CSV_TO_N8N_MAP = [
        'Full Name'              => 'Full Name',
        'Job Title'              => 'Job Title',
        'Corporate Work Email'   => 'Corporate Work Email',
        'Email Status'           => 'Email Status',
        'Company Name'           => 'Company Name',
        'Clean Root Domain'      => 'Clean Root Domain',
        'Website Status'         => 'Website Status',
        'Executive LinkedIn URL' => 'Executive LinkedIn URL',
        'Company LinkedIn Page'  => 'Company LinkedIn Page',
        'Industry Classification'=> 'Industry Classification',
        'Employee Headcount'     => 'Employee Headcount',
        'HQ Location'            => 'HQ Location',
    ];

    /**
     * Personal email domains that n8n would filter out upstream.
     */
    private const PERSONAL_DOMAINS = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'];

    /**
     * Title tier classification keywords (mirrors n8n's Clean & Tag step).
     */
    private const TIER_RULES = [
        'C-Level' => [
            'ceo', 'cfo', 'cto', 'coo', 'cmo', 'cio', 'cpo', 'cro',
            'chief', 'founder', 'co-founder', 'cofounder', 'president', 'owner', 'partner',
        ],
        'VP-Level' => [
            'vp ', 'vice president', 'svp', 'evp',
        ],
        'Director-Level' => [
            'director', 'managing director',
        ],
    ];

    public function handle(): int
    {
        $csvPath  = $this->resolveCsvPath();
        $baseUrl  = $this->resolveBaseUrl();
        $secret   = config('services.webhook.secret');
        $isDryRun = $this->option('dry-run');
        $isBulk   = $this->option('bulk');
        $delay    = (int) $this->option('delay');
        $limit    = (int) $this->option('limit');

        // ─── Validate prerequisites ─────────────────────────────
        if (!file_exists($csvPath)) {
            $this->error("CSV file not found: {$csvPath}");
            return self::FAILURE;
        }

        if (!$isDryRun && empty($secret)) {
            $this->error('WEBHOOK_SECRET is not set in .env — the webhook middleware will reject all requests.');
            $this->info('Set WEBHOOK_SECRET in your .env file or use --dry-run to preview payloads.');
            return self::FAILURE;
        }

        // ─── Parse CSV ──────────────────────────────────────────
        $leads = $this->parseCsv($csvPath);

        if (empty($leads)) {
            $this->warn('No leads found in CSV file.');
            return self::SUCCESS;
        }

        if ($limit > 0) {
            $leads = array_slice($leads, 0, $limit);
        }

        $this->info("╔══════════════════════════════════════════════════╗");
        $this->info("║         n8n Webhook Simulator                   ║");
        $this->info("╠══════════════════════════════════════════════════╣");
        $this->info("║  CSV:      " . str_pad(basename($csvPath), 38) . "║");
        $this->info("║  Leads:    " . str_pad(count($leads), 38) . "║");
        $this->info("║  Mode:     " . str_pad($isBulk ? 'Bulk POST' : 'Single POST', 38) . "║");
        $this->info("║  Target:   " . str_pad($baseUrl, 38) . "║");
        $this->info("║  Dry Run:  " . str_pad($isDryRun ? 'Yes' : 'No', 38) . "║");
        $this->info("╚══════════════════════════════════════════════════╝");
        $this->newLine();

        // ─── Transform CSV rows → n8n payloads ──────────────────
        $payloads = [];
        $filtered = 0;

        foreach ($leads as $row) {
            $payload = $this->transformToN8nPayload($row);

            if ($payload === null) {
                $filtered++;
                continue;
            }

            $payloads[] = $payload;
        }

        if ($filtered > 0) {
            $this->warn("Filtered out {$filtered} leads with personal email domains (simulating n8n upstream filter).");
        }

        if (empty($payloads)) {
            $this->warn('No leads remaining after filtering.');
            return self::SUCCESS;
        }

        // ─── Dry Run: just display ──────────────────────────────
        if ($isDryRun) {
            return $this->handleDryRun($payloads);
        }

        // ─── Send to API ────────────────────────────────────────
        if ($isBulk) {
            return $this->sendBulk($baseUrl, $secret, $payloads);
        }

        return $this->sendIndividually($baseUrl, $secret, $payloads, $delay);
    }

    /**
     * Resolve the CSV file path.
     */
    private function resolveCsvPath(): string
    {
        $csv = $this->option('csv');

        if ($csv) {
            // If relative, resolve from project root
            return str_starts_with($csv, DIRECTORY_SEPARATOR) || preg_match('/^[A-Z]:/i', $csv)
                ? $csv
                : base_path($csv);
        }

        return base_path('docs/sample_lead_list.csv');
    }

    /**
     * Resolve the API base URL, auto-detecting from .env config.
     *
     * Priority:
     * 1. --base-url CLI option
     * 2. N8N_SIMULATE_BASE_URL env var (for production / Hostinger)
     * 3. APP_URL + APP_PORT env vars (for local dev)
     */
    private function resolveBaseUrl(): string
    {
        // Explicit --base-url takes precedence
        if ($this->option('base-url')) {
            return rtrim($this->option('base-url'), '/');
        }

        // N8N_SIMULATE_BASE_URL for production deployments (e.g., Hostinger)
        $simulateUrl = env('N8N_SIMULATE_BASE_URL');
        if (!empty($simulateUrl)) {
            return rtrim($simulateUrl, '/');
        }

        // Build from APP_URL + APP_PORT for local dev
        $appUrl = config('app.url', 'http://127.0.0.1');
        $port   = $this->option('port') ?: env('APP_PORT', 8000);

        // If APP_URL already includes a port, use it as-is
        $parsedUrl = parse_url($appUrl);
        if (isset($parsedUrl['port'])) {
            return rtrim($appUrl, '/');
        }

        // Append port
        return rtrim($appUrl, '/') . ':' . $port;
    }

    /**
     * Parse a CSV file into an array of associative arrays.
     */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
            return [];
        }

        // Read header row
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return [];
        }

        // Strip BOM from first header if present
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        $headers = array_map('trim', $headers);

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($headers)) {
                $rows[] = array_combine($headers, $row);
            }
        }

        fclose($handle);
        return $rows;
    }

    /**
     * Transform a CSV row into the n8n JSON payload format.
     *
     * This simulates n8n's Clean & Tag step:
     * - Title Case on names and locations
     * - Title Tier classification from job title
     * - Personal email filtering
     * - Domain cleanup
     *
     * Returns null if the lead should be filtered out (personal email).
     */
    private function transformToN8nPayload(array $row): ?array
    {
        $email = trim($row['Corporate Work Email'] ?? '');

        // Simulate n8n personal email filter
        if ($this->isPersonalEmail($email)) {
            return null;
        }

        $payload = [];

        foreach (self::CSV_TO_N8N_MAP as $csvCol => $n8nKey) {
            $payload[$n8nKey] = trim($row[$csvCol] ?? '');
        }

        // Simulate n8n Title Tier tagging (Clean & Tag step)
        $jobTitle = $payload['Job Title'] ?? '';
        $payload['Title Tier'] = $this->classifyTitleTier($jobTitle);

        // Simulate n8n Name/Location Title Case normalization
        if (!empty($payload['Full Name'])) {
            $payload['Full Name'] = Str::title($payload['Full Name']);
        }
        if (!empty($payload['HQ Location'])) {
            $payload['HQ Location'] = Str::title($payload['HQ Location']);
        }

        // Simulate n8n domain cleanup
        if (!empty($payload['Clean Root Domain'])) {
            $domain = $payload['Clean Root Domain'];
            $domain = preg_replace('#^https?://#', '', $domain);
            $domain = preg_replace('#^www\.#', '', $domain);
            $domain = rtrim($domain, '/');
            $payload['Clean Root Domain'] = strtolower($domain);
        }

        return $payload;
    }

    /**
     * Check if an email uses a personal domain.
     */
    private function isPersonalEmail(string $email): bool
    {
        if (empty($email) || !str_contains($email, '@')) {
            return false;
        }

        $domain = strtolower(substr($email, strpos($email, '@') + 1));
        return in_array($domain, self::PERSONAL_DOMAINS, true);
    }

    /**
     * Classify a job title into a tier (mirrors n8n Clean & Tag logic).
     */
    private function classifyTitleTier(string $title): string
    {
        $lower = strtolower($title);

        foreach (self::TIER_RULES as $tier => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    return $tier;
                }
            }
        }

        return 'Other';
    }

    /**
     * Display payloads without sending (dry-run mode).
     */
    private function handleDryRun(array $payloads): int
    {
        $this->info("🔍 DRY RUN — Showing " . count($payloads) . " payloads that would be sent:");
        $this->newLine();

        foreach ($payloads as $i => $payload) {
            $this->line("─── Lead " . ($i + 1) . " ───");
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->newLine();
        }

        $this->info("✅ Dry run complete. " . count($payloads) . " leads ready to send.");
        $this->info("   Remove --dry-run to send them to the API.");

        return self::SUCCESS;
    }

    /**
     * Send leads individually (one POST per lead).
     */
    private function sendIndividually(string $baseUrl, string $secret, array $payloads, int $delayMs): int
    {
        $url = "{$baseUrl}/api/v1/webhook/leads";
        $inserted = 0;
        $duplicates = 0;
        $errors = 0;
        $results = [];

        $bar = $this->output->createProgressBar(count($payloads));
        $bar->start();

        foreach ($payloads as $i => $payload) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$secret}",
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ])->post($url, $payload);

                $status = $response->status();
                $body = $response->json();

                if ($status === 201) {
                    $inserted++;
                    $results[] = [
                        $i + 1,
                        $payload['Full Name'] ?? '—',
                        $payload['Corporate Work Email'] ?? '—',
                        '<fg=green>✔ Inserted</>',
                    ];
                } elseif ($status === 409) {
                    $duplicates++;
                    $results[] = [
                        $i + 1,
                        $payload['Full Name'] ?? '—',
                        $payload['Corporate Work Email'] ?? '—',
                        '<fg=yellow>⊘ Duplicate</>',
                    ];
                } else {
                    $errors++;
                    $errorMsg = $body['message'] ?? "HTTP {$status}";
                    $results[] = [
                        $i + 1,
                        $payload['Full Name'] ?? '—',
                        $payload['Corporate Work Email'] ?? '—',
                        "<fg=red>✘ {$errorMsg}</>",
                    ];
                }
            } catch (\Exception $e) {
                $errors++;
                $results[] = [
                    $i + 1,
                    $payload['Full Name'] ?? '—',
                    $payload['Corporate Work Email'] ?? '—',
                    "<fg=red>✘ {$e->getMessage()}</>",
                ];
            }

            $bar->advance();

            if ($delayMs > 0 && $i < count($payloads) - 1) {
                usleep($delayMs * 1000);
            }
        }

        $bar->finish();
        $this->newLine(2);

        // Display results table
        $this->table(['#', 'Name', 'Email', 'Result'], $results);

        // Summary
        $this->newLine();
        $this->info("╔══════════════════════════════════════╗");
        $this->info("║           Results Summary            ║");
        $this->info("╠══════════════════════════════════════╣");
        $this->info("║  ✔ Inserted:    " . str_pad($inserted, 20) . "║");
        $this->info("║  ⊘ Duplicates:  " . str_pad($duplicates, 20) . "║");
        $this->info("║  ✘ Errors:      " . str_pad($errors, 20) . "║");
        $this->info("║  ─ Total:       " . str_pad(count($payloads), 20) . "║");
        $this->info("╚══════════════════════════════════════╝");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Send all leads in a single bulk POST.
     */
    private function sendBulk(string $baseUrl, string $secret, array $payloads): int
    {
        $url = "{$baseUrl}/api/v1/webhook/leads/bulk";

        $this->info("Sending " . count($payloads) . " leads in a single bulk POST...");

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$secret}",
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ])->timeout(120)->post($url, ['leads' => $payloads]);

            $status = $response->status();
            $body = $response->json();

            if (in_array($status, [201, 207])) {
                $summary = $body['summary'] ?? [];

                $this->newLine();
                $this->info("╔══════════════════════════════════════╗");
                $this->info("║        Bulk Import Results           ║");
                $this->info("╠══════════════════════════════════════╣");
                $this->info("║  ✔ Inserted:    " . str_pad($summary['inserted'] ?? 0, 20) . "║");
                $this->info("║  ⊘ Duplicates:  " . str_pad($summary['duplicates'] ?? 0, 20) . "║");
                $this->info("║  ✘ Errors:      " . str_pad($summary['errors'] ?? 0, 20) . "║");
                $this->info("║  ─ Total:       " . str_pad($summary['total'] ?? count($payloads), 20) . "║");
                $this->info("╚══════════════════════════════════════╝");

                if (!empty($body['results'])) {
                    $tableRows = [];
                    foreach ($body['results'] as $r) {
                        $statusText = $r['success']
                            ? '<fg=green>✔ Inserted</>'
                            : ($r['duplicate'] ? '<fg=yellow>⊘ Duplicate</>' : '<fg=red>✘ Error</>');
                        $tableRows[] = [
                            ($r['index'] ?? '?') + 1,
                            $r['lead_id'] ?? '—',
                            $statusText,
                        ];
                    }
                    $this->table(['#', 'Lead ID', 'Result'], $tableRows);
                }

                return ($summary['errors'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
            }

            $this->error("Bulk POST failed with HTTP {$status}:");
            $this->line(json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::FAILURE;

        } catch (\Exception $e) {
            $this->error("Connection failed: {$e->getMessage()}");
            $this->info("Make sure the server is running (php artisan serve).");
            return self::FAILURE;
        }
    }
}
