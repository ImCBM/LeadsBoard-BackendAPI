<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Country;
use App\Models\Industry;
use App\Models\Lead;
use App\Models\Location;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeadIngestionService
{
    /**
     * Map of n8n payload field names → internal normalized keys.
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
        'Tags'                   => 'tags',
        'tags'                   => 'tags',
        'Tag'                    => 'tags',
        'tag'                    => 'tags',
    ];

    /**
     * Ingest a single lead into the 3NF relational database.
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

        // 3. Check for duplicate lead by corporate email
        if ($this->isDuplicate($normalized['corporate_email'] ?? null)) {
            return [
                'success'   => false,
                'lead'      => null,
                'errors'    => ['corporate_email' => 'A lead with this email already exists.'],
                'duplicate' => true,
            ];
        }

        // 4. Ingest normalized relational records atomically
        try {
            $lead = DB::transaction(function () use ($normalized, $channel) {
                // A. Industry
                $industry = null;
                if (!empty($normalized['industry_classification'])) {
                    $industry = Industry::firstOrCreate([
                        'name' => $normalized['industry_classification'],
                    ]);
                }

                // B. Country & Location
                $country = null;
                $countryName = $normalized['country'] ?? null;
                if (empty($countryName) && !empty($normalized['hq_location'])) {
                    $countryName = $this->extractCountryFromLocation($normalized['hq_location']);
                }
                if (!empty($countryName)) {
                    $country = Country::firstOrCreate([
                        'name' => $countryName,
                    ]);
                }

                $location = null;
                if (!empty($normalized['hq_location'])) {
                    $parsed = $this->parseLocationComponents($normalized['hq_location']);
                    $location = Location::firstOrCreate(
                        ['raw_location' => $normalized['hq_location']],
                        [
                            'country_id'   => $country?->id,
                            'city'         => $parsed['city'],
                            'state_region' => $parsed['state_region'],
                        ]
                    );

                    // If existing location didn't have country_id, update it
                    if ($country && !$location->country_id) {
                        $location->update(['country_id' => $country->id]);
                    }
                }

                // C. Company (Deduplicated by Clean Root Domain or Name)
                $company = null;
                if (!empty($normalized['company_name']) || !empty($normalized['clean_root_domain'])) {
                    $company = $this->resolveOrCreateCompany($normalized, $industry, $location);
                }

                // D. Lead
                $createdLead = Lead::create([
                    'company_id'             => $company?->id,
                    'full_name'              => $normalized['full_name'] ?? 'Unknown',
                    'job_title'              => $normalized['job_title'] ?? null,
                    'title_tier'             => $normalized['title_tier'] ?? 'Other',
                    'corporate_email'        => $normalized['corporate_email'],
                    'email_status'           => $normalized['email_status'] ?? null,
                    'executive_linkedin_url' => $normalized['executive_linkedin_url'] ?? null,
                    'ingestion_channel'      => $channel,
                    'status'                 => $normalized['status'] ?? 'new',
                    'notes'                  => $normalized['notes'] ?? null,
                ]);

                if (!empty($normalized['tags'])) {
                    $createdLead->syncTags($normalized['tags']);
                }

                return $createdLead;
            });

            // Eager load relationships for return
            $lead->load(['company.industry', 'company.location.country', 'tags']);

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
     * Resolve existing company or create a new one.
     */
    private function resolveOrCreateCompany(array $normalized, ?Industry $industry, ?Location $location): Company
    {
        $company = null;

        // 1. Try deduplicating by clean root domain if present
        if (!empty($normalized['clean_root_domain'])) {
            $company = Company::where('clean_root_domain', $normalized['clean_root_domain'])->first();
        }

        // 2. Fallback: try deduplicating by company name
        if (!$company && !empty($normalized['company_name'])) {
            $company = Company::where('name', $normalized['company_name'])->first();
        }

        // If found, enrich missing attributes
        if ($company) {
            $updates = [];
            if (empty($company->clean_root_domain) && !empty($normalized['clean_root_domain'])) {
                $updates['clean_root_domain'] = $normalized['clean_root_domain'];
            }
            if (empty($company->website_status) && !empty($normalized['website_status'])) {
                $updates['website_status'] = $normalized['website_status'];
            }
            if (empty($company->company_linkedin_page) && !empty($normalized['company_linkedin_page'])) {
                $updates['company_linkedin_page'] = $normalized['company_linkedin_page'];
            }
            if (!$company->industry_id && $industry) {
                $updates['industry_id'] = $industry->id;
            }
            if (!$company->location_id && $location) {
                $updates['location_id'] = $location->id;
            }
            if ($company->employee_headcount === null && isset($normalized['employee_headcount'])) {
                $updates['employee_headcount'] = $normalized['employee_headcount'];
            }

            if (!empty($updates)) {
                $company->update($updates);
            }

            return $company;
        }

        // Otherwise, create new Company
        return Company::create([
            'name'                  => $normalized['company_name'] ?? ($normalized['clean_root_domain'] ?? 'Unknown Company'),
            'clean_root_domain'     => $normalized['clean_root_domain'] ?? null,
            'website_status'        => $normalized['website_status'] ?? null,
            'company_linkedin_page' => $normalized['company_linkedin_page'] ?? null,
            'industry_id'           => $industry?->id,
            'location_id'           => $location?->id,
            'employee_headcount'    => $normalized['employee_headcount'] ?? null,
        ]);
    }

    /**
     * Map n8n-style field names to internal snake_case keys.
     */
    private function mapFields(array $data): array
    {
        $mapped = [];

        foreach ($data as $key => $value) {
            if (isset(self::FIELD_MAP[$key])) {
                $mapped[self::FIELD_MAP[$key]] = $value;
            } elseif (in_array($key, self::FIELD_MAP, true)) {
                $mapped[$key] = $value;
            } elseif (in_array($key, ['country', 'ingestion_channel', 'status', 'notes', 'company_id', 'tags'])) {
                $mapped[$key] = $value;
            }
        }

        return $mapped;
    }

    /**
     * Normalize and clean the mapped data.
     */
    private function normalize(array $data): array
    {
        $data = array_map(fn($v) => is_string($v) ? trim($v) : $v, $data);

        // Normalize full name: trim only, preserve original casing
        // (Str::title() destroys interior capitals: McDonald -> Mcdonald, O'Brien -> O'brien)
        if (!empty($data['full_name'])) {
            $data['full_name'] = trim($data['full_name']);
        }

        // Normalize email to lowercase
        if (!empty($data['corporate_email'])) {
            $data['corporate_email'] = Str::lower($data['corporate_email']);
        }

        // Normalize title tier
        if (!empty($data['title_tier'])) {
            $data['title_tier'] = $this->normalizeTitleTier($data['title_tier']);
        } else {
            $data['title_tier'] = 'Other';
        }

        // Parse employee headcount
        if (isset($data['employee_headcount'])) {
            $headcount = $data['employee_headcount'];
            if ($headcount === '' || $headcount === null) {
                $data['employee_headcount'] = null;
            } else {
                $data['employee_headcount'] = (int) preg_replace('/[^0-9]/', '', (string) $headcount);
            }
        }

        // Clean root domain
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
                $data[$field] = null;
            }
        }

        // Convert empty strings to null for nullable fields
        $nullableFields = [
            'job_title', 'email_status', 'company_name', 'clean_root_domain', 'website_status',
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

        foreach ($known as $valid) {
            if (strcasecmp($tier, $valid) === 0) {
                return $valid;
            }
        }

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
     */
    private function extractCountryFromLocation(string $location): ?string
    {
        $parts = array_map('trim', explode(',', $location));
        if (count($parts) >= 2) {
            return Str::title(end($parts));
        }

        return null;
    }

    /**
     * Parse location into city, state_region.
     */
    private function parseLocationComponents(string $location): array
    {
        $parts = array_map('trim', explode(',', $location));
        $city = null;
        $stateRegion = null;

        if (count($parts) >= 1) {
            $city = Str::title($parts[0]);
        }
        if (count($parts) >= 2) {
            $stateRegion = Str::title($parts[1]);
        }

        return [
            'city'         => $city,
            'state_region' => $stateRegion,
        ];
    }
}
