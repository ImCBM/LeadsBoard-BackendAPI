<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    use HasFactory;

    const TITLE_TIERS = ['C-Level', 'VP-Level', 'Director-Level', 'Other'];
    const STATUSES = ['new', 'reviewed', 'qualified', 'rejected'];
    const INGESTION_CHANNELS = ['n8n', 'manual', 'api', 'csv_import'];

    protected $fillable = [
        'company_id',
        'full_name',
        'job_title',
        'title_tier',
        'corporate_email',
        'email_status',
        'executive_linkedin_url',
        'ingestion_channel',
        'status',
        'notes',
    ];

    /**
     * The accessors to append to the model's array and JSON form.
     * Guarantees 100% backward compatibility for API & Dashboard consumers.
     */
    protected $appends = [
        'company_name',
        'clean_root_domain',
        'website_status',
        'company_linkedin_page',
        'industry_classification',
        'employee_headcount',
        'hq_location',
        'country',
    ];

    protected $with = [
        'company.industry',
        'company.location.country',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // ─── Relationships ──────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // ─── Accessors ──────────────────────────────────────────────

    protected function companyName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->company?->name,
        );
    }

    protected function cleanRootDomain(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->company?->clean_root_domain,
        );
    }

    protected function websiteStatus(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->company?->website_status,
        );
    }

    protected function companyLinkedinPage(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->company?->company_linkedin_page,
        );
    }

    protected function industryClassification(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->company?->industry?->name,
        );
    }

    protected function employeeHeadcount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->company?->employee_headcount,
        );
    }

    protected function hqLocation(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->company?->location?->raw_location,
        );
    }

    protected function country(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->company?->location?->country?->name,
        );
    }

    const HEADCOUNT_RANGES = [
        '1-10'     => '1 – 10 (Startup / Micro)',
        '11-50'    => '11 – 50 (Small Team)',
        '51-200'   => '51 – 200 (Mid-Market)',
        '201-500'  => '201 – 500 (Upper Mid-Market)',
        '501-1000' => '501 – 1,000 (Large)',
        '1000+'    => '1,000+ (Enterprise)',
    ];

    // ─── Query Scopes ───────────────────────────────────────────

    /**
     * Filter by industry classification.
     */
    public function scopeByIndustry(Builder $query, ?string $industry): Builder
    {
        return $query->when($industry, function ($q) use ($industry) {
            $q->whereHas('company.industry', fn($iq) => $iq->where('name', $industry));
        });
    }

    /**
     * Filter by title tier.
     */
    public function scopeByTitleTier(Builder $query, ?string $tier): Builder
    {
        return $query->when($tier, fn($q) => $q->where('title_tier', $tier));
    }

    /**
     * Filter by lead status.
     */
    public function scopeByStatus(Builder $query, ?string $status): Builder
    {
        return $query->when($status, fn($q) => $q->where('status', $status));
    }

    /**
     * Filter by country.
     */
    public function scopeByCountry(Builder $query, ?string $country): Builder
    {
        return $query->when($country, function ($q) use ($country) {
            $countries = array_filter(array_map('trim', explode(',', $country)));
            if (count($countries) > 0) {
                $q->whereHas('company.location.country', fn($cq) => $cq->whereIn('name', $countries));
            }
        });
    }

    /**
     * Filter by ingestion channel.
     */
    public function scopeByChannel(Builder $query, ?string $channel): Builder
    {
        return $query->when($channel, fn($q) => $q->where('ingestion_channel', $channel));
    }

    /**
     * Filter by employee headcount range or min/max.
     * Presets: '1-10', '11-50', '51-200', '201-500', '501-1000', '1000+'
     */
    public function scopeByHeadcount(Builder $query, ?string $range = null, ?int $min = null, ?int $max = null): Builder
    {
        if ($range) {
            if ($range === '1000+' || $range === '1000-plus' || $range === '1001+') {
                $min = 1000;
                $max = null;
            } elseif (str_contains($range, '-')) {
                [$rMin, $rMax] = explode('-', $range, 2);
                $min = (int)$rMin;
                $max = (int)$rMax;
            }
        }

        return $query->when($min !== null || $max !== null, function ($q) use ($min, $max) {
            $q->whereHas('company', function ($cq) use ($min, $max) {
                if ($min !== null && $max !== null) {
                    $cq->whereBetween('companies.employee_headcount', [$min, $max]);
                } elseif ($min !== null) {
                    $cq->where('companies.employee_headcount', '>=', $min);
                } elseif ($max !== null) {
                    $cq->where('companies.employee_headcount', '<=', $max);
                }
            });
        });
    }

    /**
     * Filter by website status (e.g. 200 OK, error, offline).
     */
    public function scopeByWebsiteStatus(Builder $query, ?string $status): Builder
    {
        return $query->when($status, function ($q) use ($status) {
            if ($status === '200' || $status === '200_ok' || $status === 'active') {
                $q->whereHas('company', fn($cq) => $cq->where('website_status', 'LIKE', '%200%'));
            } elseif ($status === 'error' || $status === 'offline') {
                $q->whereHas('company', fn($cq) => $cq->where('website_status', 'NOT LIKE', '%200%')->orWhereNull('website_status'));
            } else {
                $q->whereHas('company', fn($cq) => $cq->where('website_status', 'LIKE', "%{$status}%"));
            }
        });
    }

    /**
     * Filter by email status.
     */
    public function scopeByEmailStatus(Builder $query, ?string $status): Builder
    {
        return $query->when($status, function ($q) use ($status) {
            if ($status === 'valid') {
                $q->where('email_status', 'LIKE', '%Valid%');
            } elseif ($status === 'invalid') {
                $q->where('email_status', 'LIKE', '%Invalid%');
            } else {
                $q->where('email_status', 'LIKE', "%{$status}%");
            }
        });
    }

    /**
     * Order-independent, multi-token search across name, email, company, domain,
     * job title, tier, industry, location, country, and status.
     * Supports queries like "CompanyX, John, CountryX, Data Science".
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term) || trim($term) === '') {
            return $query;
        }

        // Split by comma if present, otherwise split by whitespace if multiple words
        $trimmed = trim($term);
        if (str_contains($trimmed, ',')) {
            $tokens = array_filter(array_map('trim', explode(',', $trimmed)));
        } else {
            $tokens = [$trimmed];
        }

        return $query->where(function ($q) use ($tokens) {
            foreach ($tokens as $t) {
                if ($t === '') continue;
                $q->where(function ($inner) use ($t) {
                    $inner->where('leads.full_name', 'LIKE', "%{$t}%")
                          ->orWhere('leads.corporate_email', 'LIKE', "%{$t}%")
                          ->orWhere('leads.job_title', 'LIKE', "%{$t}%")
                          ->orWhere('leads.title_tier', 'LIKE', "%{$t}%")
                          ->orWhere('leads.email_status', 'LIKE', "%{$t}%")
                          ->orWhere('leads.status', 'LIKE', "%{$t}%")
                          ->orWhereHas('company', function ($cq) use ($t) {
                              $cq->where('companies.name', 'LIKE', "%{$t}%")
                                 ->orWhere('companies.clean_root_domain', 'LIKE', "%{$t}%")
                                 ->orWhere('companies.website_status', 'LIKE', "%{$t}%")
                                 ->orWhereHas('industry', fn($iq) => $iq->where('industries.name', 'LIKE', "%{$t}%"))
                                 ->orWhereHas('location', function ($lq) use ($t) {
                                     $lq->where('locations.raw_location', 'LIKE', "%{$t}%")
                                        ->orWhere('locations.city', 'LIKE', "%{$t}%")
                                        ->orWhere('locations.state_region', 'LIKE', "%{$t}%")
                                        ->orWhereHas('country', fn($ctq) => $ctq->where('countries.name', 'LIKE', "%{$t}%"));
                                 });
                          });
                });
            }
        });
    }

    /**
     * Filter by date range on created_at.
     */
    public function scopeDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('created_at', '<=', $to));
    }

    /**
     * Apply all filters from request parameters at once.
     */
    public function scopeApplyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->search($filters['search'] ?? null)
            ->byIndustry($filters['industry'] ?? null)
            ->byTitleTier($filters['title_tier'] ?? null)
            ->byStatus($filters['status'] ?? null)
            ->byCountry($filters['country'] ?? null)
            ->byChannel($filters['ingestion_channel'] ?? null)
            ->byHeadcount(
                $filters['headcount_range'] ?? null,
                (isset($filters['headcount_min']) && $filters['headcount_min'] !== '') ? (int)$filters['headcount_min'] : null,
                (isset($filters['headcount_max']) && $filters['headcount_max'] !== '') ? (int)$filters['headcount_max'] : null
            )
            ->byWebsiteStatus($filters['website_status'] ?? null)
            ->byEmailStatus($filters['email_status'] ?? null)
            ->dateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null);
    }
}
