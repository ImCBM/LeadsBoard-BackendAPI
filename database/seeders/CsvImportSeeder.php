<?php

namespace Database\Seeders;

use App\Services\LeadIngestionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the database from a CSV file using the LeadIngestionService.
 *
 * Unlike LeadSeeder (which hardcodes lead data), this seeder dynamically
 * reads any CSV in the n8n payload format, applies the same normalization
 * and dedup logic the webhook would, and inserts via the ingestion service.
 *
 * Usage:
 *   php artisan db:seed --class=CsvImportSeeder
 *
 * Set the CSV path via the N8N_SIMULATE_CSV_PATH env variable,
 * or it defaults to docs/sample_lead_list.csv.
 */
class CsvImportSeeder extends Seeder
{
    /**
     * Title tier classification keywords (mirrors n8n's Clean & Tag logic).
     */
    private const TIER_RULES = [
        'C-Level' => [
            'ceo', 'cfo', 'cto', 'coo', 'cmo', 'cio', 'cpo', 'cro',
            'chief', 'founder', 'co-founder', 'cofounder', 'president', 'owner', 'partner',
        ],
        'VP-Level' => ['vp ', 'vice president', 'svp', 'evp'],
        'Director-Level' => ['director', 'managing director'],
    ];

    public function run(): void
    {
        $csvPath = env('N8N_SIMULATE_CSV_PATH', base_path('docs/sample_lead_list.csv'));

        if (!file_exists($csvPath)) {
            $this->command->error("CSV file not found: {$csvPath}");
            $this->command->info("Set N8N_SIMULATE_CSV_PATH in .env or place your CSV at docs/sample_lead_list.csv");
            return;
        }

        $ingestionService = app(LeadIngestionService::class);
        $rows = $this->parseCsv($csvPath);

        if (empty($rows)) {
            $this->command->warn('No rows found in CSV file.');
            return;
        }

        $inserted = 0;
        $duplicates = 0;
        $errors = 0;

        foreach ($rows as $row) {
            // Transform to n8n payload format
            $payload = $this->transformToN8nPayload($row);

            if ($payload === null) {
                continue; // Personal email filtered
            }

            $result = $ingestionService->ingest($payload, 'csv_import');

            if ($result['success']) {
                $inserted++;
            } elseif ($result['duplicate']) {
                $duplicates++;
            } else {
                $errors++;
            }
        }

        $this->command->info("CSV Import complete: {$inserted} inserted, {$duplicates} duplicates, {$errors} errors.");
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

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return [];
        }

        // Strip BOM if present
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
     * Transform a CSV row into n8n payload format.
     * Returns null for personal emails (filtered out).
     */
    private function transformToN8nPayload(array $row): ?array
    {
        $email = trim($row['Corporate Work Email'] ?? '');

        // Filter personal emails
        if (!empty($email) && str_contains($email, '@')) {
            $domain = strtolower(substr($email, strpos($email, '@') + 1));
            if (in_array($domain, ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'], true)) {
                return null;
            }
        }

        // Build n8n-style payload (human-readable keys)
        $payload = [
            'Full Name'              => trim($row['Full Name'] ?? ''),
            'Job Title'              => trim($row['Job Title'] ?? ''),
            'Corporate Work Email'   => trim($row['Corporate Work Email'] ?? ''),
            'Email Status'           => trim($row['Email Status'] ?? ''),
            'Company Name'           => trim($row['Company Name'] ?? ''),
            'Clean Root Domain'      => strtolower(trim($row['Clean Root Domain'] ?? '')),
            'Website Status'         => trim($row['Website Status'] ?? ''),
            'Executive LinkedIn URL' => trim($row['Executive LinkedIn URL'] ?? ''),
            'Company LinkedIn Page'  => trim($row['Company LinkedIn Page'] ?? ''),
            'Industry Classification'=> trim($row['Industry Classification'] ?? ''),
            'Employee Headcount'     => trim($row['Employee Headcount'] ?? ''),
            'HQ Location'            => Str::title(trim($row['HQ Location'] ?? '')),
        ];

        // Add Title Tier (computed by n8n's Clean & Tag step)
        $payload['Title Tier'] = $this->classifyTitleTier($payload['Job Title']);

        return $payload;
    }

    /**
     * Classify a job title into a tier.
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
}
