<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Support\Str;

class LeadIngestionService
{
    /**
     * Map of n8n payload field names → database column names.
     * The n8n team uses human-readable keys with spaces;
     * we normalize them to snake_case columns.
     */
    private const FIELD_MAP = [
        'Full Name'              => 'full_name',
        'Job Title'              => 'job_title',
        'Title Tier'             => 'title_tier',
        'Corporate Work Email'   => 'corporate_email',
        'Email Status'           => 'email_status',
        'Company Name'           => 'company_name',
        'Clean Root Domain'      => 'clean_root_domain',
        'Website Status'         => 'website_status',
        'Executive LinkedIn URL' => 'executive_linkedin_url',
        'Company LinkedIn Page'  => 'company_linkedin_page',
        'Industry Classification'=> 'industry_classification',
        'Employee Headcount'     => 'employee_headcount',
        'HQ Location'            => 'hq_location',
    ];

    /**
     * Ingest a single lead from raw payload data.
     *
     * Accepts both n8n-style keys ("Full Name") and snake_case keys ("full_name").
     *
     * @param  array  $rawData  The incoming payload
     * @param  string $channel  The ingestion channel (n8n, manual, api, csv_import)
     * @return array{success: bool, lead: ?Lead, errors: array, duplicate: bool}
     */
    public function ingest(array $rawData, string $channel = 'n8n'): array
    {
        // 1. Map field names (handles both n8n-style and snake_case keys)
        $mapped = $this->mapFields($rawData);

        // 2. Normalize data
        $normalized = $this->normalize($mapped);

        // 3. Check for duplicate by corporate email
        if ($this->isDuplicate($normalized['corporate_email'] ?? null)) {
            return [
                'success'   => false,
                'lead'      => null,
                'errors'    => ['corporate_email' => 'A lead with this email already exists.'],
                'duplicate' => true,
            ];
        }

        // 4. Set metadata
        $normalized['ingestion_channel'] = $channel;
        $normalized['status'] = 'new';

        // 5. Resolve country from HQ location if not explicitly provided
        if (empty($normalized['country']) && !empty($normalized['hq_location'])) {
            $normalized['country'] = $this->extractCountryFromLocation($normalized['hq_location']);
        }

        // 6. Create the lead
        try {
            $lead = Lead::create($normalized);
            return [
                'success'   => true,
                'lead'      => $lead,
                'errors'    => [],
                'duplicate' => false,
            ];
        } catch (\Exception $e) {
            return [
                'success'   => false,
                'lead'      => null,
                'errors'    => ['database' => $e->getMessage()],
                'duplicate' => false,
            ];
        }
    }

    /**
     * Ingest multiple leads at once.
     *
     * @param  array  $items   Array of raw lead payloads
     * @param  string $channel The ingestion channel
     * @return array{inserted: int, duplicates: int, errors: int, results: array}
     */
    public function bulkIngest(array $items, string $channel = 'n8n'): array
    {
        $inserted   = 0;
        $duplicates = 0;
        $errors     = 0;
        $results    = [];

        foreach ($items as $index => $item) {
            $result = $this->ingest($item, $channel);

            if ($result['success']) {
                $inserted++;
            } elseif ($result['duplicate']) {
                $duplicates++;
            } else {
                $errors++;
            }

            $results[] = [
                'index'     => $index,
                'success'   => $result['success'],
                'duplicate' => $result['duplicate'],
                'errors'    => $result['errors'],
                'lead_id'   => $result['lead']?->id,
            ];
        }

        return [
            'inserted'   => $inserted,
            'duplicates' => $duplicates,
            'errors'     => $errors,
            'results'    => $results,
        ];
    }

    /**
     * Map n8n-style field names to snake_case database columns.
     * Also accepts already-mapped snake_case keys for flexibility.
     */
    private function mapFields(array $data): array
    {
        $mapped = [];

        foreach ($data as $key => $value) {
            // Check if it's an n8n-style key
            if (isset(self::FIELD_MAP[$key])) {
                $mapped[self::FIELD_MAP[$key]] = $value;
            }
            // Check if it's already a valid snake_case column
            elseif (in_array($key, self::FIELD_MAP, true)) {
                $mapped[$key] = $value;
            }
            // Pass through other known columns (country, status, notes, etc.)
            elseif (in_array($key, ['country', 'ingestion_channel', 'status', 'notes'])) {
                $mapped[$key] = $value;
            }
            // Unknown fields are silently ignored
        }

        return $mapped;
    }

    /**
     * Normalize and clean the mapped data.
     */
    private function normalize(array $data): array
    {
        // Trim all string values
        $data = array_map(fn($v) => is_string($v) ? trim($v) : $v, $data);

        // Normalize full name to Title Case
        if (!empty($data['full_name'])) {
            $data['full_name'] = Str::title($data['full_name']);
        }

        // Normalize email to lowercase
        if (!empty($data['corporate_email'])) {
            $data['corporate_email'] = Str::lower($data['corporate_email']);
        }

        // Normalize title tier to known values
        if (!empty($data['title_tier'])) {
            $data['title_tier'] = $this->normalizeTitleTier($data['title_tier']);
        } else {
            $data['title_tier'] = 'Other';
        }

        // Parse employee headcount to integer (may arrive as string or empty)
        if (isset($data['employee_headcount'])) {
            $headcount = $data['employee_headcount'];
            if ($headcount === '' || $headcount === null) {
                $data['employee_headcount'] = null;
            } else {
                $data['employee_headcount'] = (int) preg_replace('/[^0-9]/', '', (string) $headcount);
            }
        }

        // Clean root domain: strip protocol, www, trailing slashes
        if (!empty($data['clean_root_domain'])) {
            $domain = $data['clean_root_domain'];
            $domain = preg_replace('#^https?://#', '', $domain);
            $domain = preg_replace('#^www\.#', '', $domain);
            $domain = rtrim($domain, '/');
            $data['clean_root_domain'] = Str::lower($domain);
        }

        // Sanitize LinkedIn URLs
        foreach (['executive_linkedin_url', 'company_linkedin_page'] as $field) {
            if (!empty($data[$field]) && !filter_var($data[$field], FILTER_VALIDATE_URL)) {
                $data[$field] = null; // Invalid URL → null
            }
        }

        // Convert empty strings to null for nullable fields
        $nullableFields = [
            'job_title', 'email_status', 'clean_root_domain', 'website_status',
            'executive_linkedin_url', 'company_linkedin_page', 'industry_classification',
            'hq_location', 'country', 'notes',
        ];
        foreach ($nullableFields as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        return $data;
    }

    /**
     * Normalize title tier to one of the known values.
     */
    private function normalizeTitleTier(string $tier): string
    {
        $tier = trim($tier);
        $known = Lead::TITLE_TIERS;

        // Exact match (case-insensitive)
        foreach ($known as $valid) {
            if (strcasecmp($tier, $valid) === 0) {
                return $valid;
            }
        }

        // Fuzzy match: check if the tier contains a known keyword
        $lowerTier = Str::lower($tier);
        if (Str::contains($lowerTier, 'c-level') || Str::contains($lowerTier, 'clevel')) {
            return 'C-Level';
        }
        if (Str::contains($lowerTier, 'vp') || Str::contains($lowerTier, 'vice president')) {
            return 'VP-Level';
        }
        if (Str::contains($lowerTier, 'director')) {
            return 'Director-Level';
        }

        return 'Other';
    }

    /**
     * Check if a lead with this email already exists.
     */
    private function isDuplicate(?string $email): bool
    {
        if (empty($email)) {
            return false;
        }

        return Lead::where('corporate_email', Str::lower($email))->exists();
    }

    /**
     * Extract country from HQ location string.
     * The n8n payload sends location as "City, Region, Country".
     * This is a basic parser — a real geocoding API would replace this.
     */
    private function extractCountryFromLocation(string $location): ?string
    {
        $parts = array_map('trim', explode(',', $location));

        // The country is typically the last segment
        if (count($parts) >= 2) {
            return Str::title(end($parts));
        }

        return null;
    }
}
