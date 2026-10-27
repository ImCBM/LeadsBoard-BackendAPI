<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFactory;

    const TYPE_PUBLIC = 'public';
    const TYPE_SYSTEM = 'system';

    protected $fillable = [
        'name',
        'slug',
        'type',
        'color',
        'description',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tag) {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    /**
     * Leads associated with this tag.
     */
    public function leads(): BelongsToMany
    {
        return $this->belongsToMany(Lead::class, 'lead_tag')->withTimestamps();
    }

    /**
     * Scope to public tags.
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_PUBLIC);
    }

    /**
     * Scope to system / internal developer tags.
     */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_SYSTEM);
    }

    /**
     * Find or create a tag by name with an optional default type and color.
     */
    public static function findOrCreateByName(string $name, string $type = self::TYPE_PUBLIC, ?string $color = null): self
    {
        $cleanName = trim($name);
        $slug = Str::slug($cleanName);

        $defaultName = null;
        $defaultColor = $type === self::TYPE_SYSTEM ? '#ef4444' : '#3b82f6';
        if ($slug === 'test') {
            $type = self::TYPE_SYSTEM;
            $defaultColor = '#ef4444'; // Red
            $defaultName = 'Test';
        } elseif ($slug === 'demo') {
            $type = self::TYPE_SYSTEM;
            $defaultColor = '#f59e0b'; // Amber
            $defaultName = 'Demo';
        } elseif ($slug === 'sample') {
            $type = self::TYPE_SYSTEM;
            $defaultColor = '#64748b'; // Slate
            $defaultName = 'Sample';
        } elseif ($slug === 'vip') {
            $type = self::TYPE_PUBLIC;
            $defaultColor = '#10b981'; // Emerald
            $defaultName = 'VIP';
        } elseif ($slug === 'high-priority') {
            $type = self::TYPE_PUBLIC;
            $defaultColor = '#8b5cf6'; // Violet
            $defaultName = 'High Priority';
        }

        $formattedName = $defaultName ?: (ctype_lower(str_replace(['-', '_', ' '], '', $cleanName)) ? Str::title(str_replace(['-', '_'], ' ', $cleanName)) : $cleanName);

        return static::firstOrCreate(
            ['slug' => $slug],
            [
                'name'  => $formattedName,
                'type'  => $type,
                'color' => $color ?: $defaultColor,
            ]
        );
    }
}
