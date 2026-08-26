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
            $q->whereHas('company.location.country', fn($cq) => $cq->where('name', $country));
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
     * Full-text search across name, email, company, job title, industry, location, country.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, function ($q) use ($term) {
            $q->where(function ($inner) use ($term) {
                $inner->where('leads.full_name', 'LIKE', "%{$term}%")
                      ->orWhere('leads.corporate_email', 'LIKE', "%{$term}%")
                      ->orWhere('leads.job_title', 'LIKE', "%{$term}%")
                      ->orWhereHas('company', function ($cq) use ($term) {
                          $cq->where('companies.name', 'LIKE', "%{$term}%")
                             ->orWhere('companies.clean_root_domain', 'LIKE', "%{$term}%")
                             ->orWhereHas('industry', fn($iq) => $iq->where('industries.name', 'LIKE', "%{$term}%"))
                             ->orWhereHas('location', function ($lq) use ($term) {
                                 $lq->where('locations.raw_location', 'LIKE', "%{$term}%")
                                    ->orWhereHas('country', fn($ctq) => $ctq->where('countries.name', 'LIKE', "%{$term}%"));
                             });
                      });
            });
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
            ->dateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null);
    }
}
