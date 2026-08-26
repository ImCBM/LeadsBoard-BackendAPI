<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'clean_root_domain',
        'website_status',
        'company_linkedin_page',
        'industry_id',
        'location_id',
        'employee_headcount',
    ];

    protected function casts(): array
    {
        return [
            'employee_headcount' => 'integer',
        ];
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
