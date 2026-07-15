<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Static CMS pages (policies, terms, travel info) served at /pages/{slug}.
 */
#[Fillable([
    'title',
    'slug',
    'content',
    'excerpt',
    'featured_image_path',
    'seo_title',
    'seo_description',
    'canonical_url',
    'robots',
    'status',
    'show_in_footer',
    'footer_group',
    'footer_label',
    'footer_sort_order',
    'open_in_new_tab',
    'created_by',
    'updated_by',
    'published_at',
])]
class CmsPage extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const ROBOTS_INDEX = 'index';

    public const ROBOTS_NOINDEX = 'noindex';

    public const FOOTER_GROUPS = [
        'company',
        'policies',
        'support',
        'travel_info',
        'agent_b2b',
    ];

    protected function casts(): array
    {
        return [
            'show_in_footer' => 'boolean',
            'open_in_new_tab' => 'boolean',
            'published_at' => 'datetime',
            'footer_sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @param  Builder<CmsPage>  $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /** @param  Builder<CmsPage>  $query */
    public function scopeForFooter(Builder $query): Builder
    {
        return $query
            ->active()
            ->where('show_in_footer', true)
            ->orderBy('footer_sort_order')
            ->orderBy('title');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getRouteUrlAttribute(): string
    {
        return route('pages.show', $this->slug);
    }

    public function featuredImageUrl(): ?string
    {
        if (! is_string($this->featured_image_path) || $this->featured_image_path === '') {
            return null;
        }

        return asset('storage/'.$this->featured_image_path);
    }
}
