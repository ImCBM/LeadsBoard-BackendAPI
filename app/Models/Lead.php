<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Lead extends Model
{
    use HasFactory;

    const TITLE_TIERS = ['C-Level', 'VP-Level', 'Director-Level', 'Other'];
    const STATUSES = ['new', 'reviewed', 'qualified', 'rejected'];
    const INGESTION_CHANNELS = ['n8n', 'manual', 'api', 'csv_import', 'csv_upload'];

    protected $fillable = [
        'company_id',
        'full_name',
        'job_title',
        'title_tier',
        'corporate_email',
        'contact_number',
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
        'tag_names',
    ];

    protected $with = [
        'company.industry',
        'company.location.country',
        'tags',
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

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'lead_tag')->withTimestamps();
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

    protected function tagNames(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->tags ? $this->tags->pluck('name')->toArray() : [],
        );
    }

    // ─── Tag Helper Methods ─────────────────────────────────────

    /**
     * Sync tags by array of names, slugs, or IDs.
     */
    public function syncTags(array|string|null $tags, string $defaultType = Tag::TYPE_PUBLIC): self
    {
        if (is_null($tags)) {
            return $this;
        }
        $tagIds = $this->resolveTagIds($tags, $defaultType);
        $this->tags()->sync($tagIds);
        return $this;
    }

    /**
     * Attach tags without detaching existing ones.
     */
    public function attachTags(array|string $tags, string $defaultType = Tag::TYPE_PUBLIC): self
    {
        $tagIds = $this->resolveTagIds($tags, $defaultType);
        $this->tags()->syncWithoutDetaching($tagIds);
        return $this;
    }

    /**
     * Detach tags from lead.
     */
    public function detachTags(array|string $tags): self
    {
        $tagIds = $this->resolveTagIds($tags);
        $this->tags()->detach($tagIds);
        return $this;
    }

    /**
     * Resolve string/array/object tags into an array of database Tag IDs.
     */
    private function resolveTagIds(array|string $tags, string $defaultType = Tag::TYPE_PUBLIC): array
    {
        if (is_string($tags)) {
            $tags = array_filter(array_map('trim', explode(',', $tags)));
        }

        $tagIds = [];
        foreach ($tags as $tagItem) {
            if ($tagItem instanceof Tag) {
                $tagIds[] = $tagItem->id;
            } elseif (is_numeric($tagItem)) {
                $tagIds[] = (int) $tagItem;
            } elseif (is_string($tagItem) && !empty($tagItem)) {
                $tag = Tag::findOrCreateByName($tagItem, $defaultType);
                $tagIds[] = $tag->id;
            } elseif (is_array($tagItem) && isset($tagItem['name'])) {
                $type = $tagItem['type'] ?? $defaultType;
                $color = $tagItem['color'] ?? null;
                $tag = Tag::findOrCreateByName($tagItem['name'], $type, $color);
                $tagIds[] = $tag->id;
            }
        }

        return array_values(array_unique($tagIds));
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
     * Filter by website status.
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
     * Filter by a single tag (name or slug).
     */
    public function scopeByTag(Builder $query, ?string $tag): Builder
    {
        return $query->when($tag, function ($q) use ($tag) {
            $slug = Str::slug(trim($tag));
            $q->whereHas('tags', fn($tq) => $tq->where('slug', $slug)->orWhere('name', 'LIKE', $tag));
        });
    }

    /**
     * Filter by multiple tags (any of the listed tags).
     */
    public function scopeByTags(Builder $query, array|string|null $tags): Builder
    {
        if (empty($tags)) {
            return $query;
        }

        if (is_string($tags)) {
            $tags = array_filter(array_map('trim', explode(',', $tags)));
        }

        $slugs = array_map(fn($t) => Str::slug(trim($t)), $tags);

        return $query->whereHas('tags', fn($tq) => $tq->whereIn('slug', $slugs)->orWhereIn('name', $tags));
    }

    /**
     * Filter leads that do NOT have the specified tags.
     */
    public function scopeWithoutTags(Builder $query, array|string|null $tags): Builder
    {
        if (empty($tags)) {
            return $query;
        }

        if (is_string($tags)) {
            $tags = array_filter(array_map('trim', explode(',', $tags)));
        }

        $slugs = array_map(fn($t) => Str::slug(trim($t)), $tags);

        return $query->whereDoesntHave('tags', fn($tq) => $tq->whereIn('slug', $slugs)->orWhereIn('name', $tags));
    }

    /**
     * Order-independent, multi-token search across name, email, company, domain,
     * job title, tier, industry, location, country, status, and tags.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term) || trim($term) === '') {
            return $query;
        }

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
                          ->orWhere('leads.contact_number', 'LIKE', "%{$t}%")
                          ->orWhere('leads.job_title', 'LIKE', "%{$t}%")
                          ->orWhere('leads.title_tier', 'LIKE', "%{$t}%")
                          ->orWhere('leads.email_status', 'LIKE', "%{$t}%")
                          ->orWhere('leads.status', 'LIKE', "%{$t}%")
                          ->orWhereHas('tags', fn($tq) => $tq->where('tags.name', 'LIKE', "%{$t}%")->orWhere('tags.slug', 'LIKE', "%{$t}%"))
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
            ->byTag($filters['tag'] ?? null)
            ->byTags($filters['tags'] ?? null)
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
