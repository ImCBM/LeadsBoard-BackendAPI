<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Lead extends Model
{
    use HasFactory;

    const TITLE_TIERS = ['C-Level', 'VP-Level', 'Director-Level', 'Other'];
    const STATUSES = ['new', 'reviewed', 'qualified', 'rejected'];
    const INGESTION_CHANNELS = ['n8n', 'manual', 'api', 'csv_import'];

    protected $fillable = [
        'full_name',
        'job_title',
        'title_tier',
        'corporate_email',
        'email_status',
        'company_name',
        'clean_root_domain',
        'website_status',
        'executive_linkedin_url',
        'company_linkedin_page',
        'industry_classification',
        'employee_headcount',
        'hq_location',
        'country',
        'ingestion_channel',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'employee_headcount' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // ─── Query Scopes ───────────────────────────────────────────

    /**
     * Filter by industry classification.
     */
    public function scopeByIndustry(Builder $query, ?string $industry): Builder
    {
        return $query->when($industry, fn($q) => $q->where('industry_classification', $industry));
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
        return $query->when($country, fn($q) => $q->where('country', $country));
    }

    /**
     * Filter by ingestion channel.
     */
    public function scopeByChannel(Builder $query, ?string $channel): Builder
    {
        return $query->when($channel, fn($q) => $q->where('ingestion_channel', $channel));
    }

    /**
     * Full-text search across name, email, company, job title, industry, location.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, function ($q) use ($term) {
            $q->where(function ($inner) use ($term) {
                $inner->where('full_name', 'LIKE', "%{$term}%")
                      ->orWhere('corporate_email', 'LIKE', "%{$term}%")
                      ->orWhere('company_name', 'LIKE', "%{$term}%")
                      ->orWhere('job_title', 'LIKE', "%{$term}%")
                      ->orWhere('industry_classification', 'LIKE', "%{$term}%")
                      ->orWhere('hq_location', 'LIKE', "%{$term}%")
                      ->orWhere('country', 'LIKE', "%{$term}%");
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
