<?php

namespace App\Models;

use App\Traits\HasTranslations;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Curated marketing testimonial. Distinct from `Review`, which is guest UGC —
 * see the migration for why the two stay apart.
 */
class Testimonial extends Model
{
    use HasFactory, HasUuid, HasTranslations, LogsActivity;

    protected $translatable = ['author_title', 'quote'];

    protected $fillable = [
        'author_name',
        'author_title',
        'quote',
        'rating',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'rating'    => 'integer',
    ];

    /**
     * Optional author portrait, through the same `Media` morph every other CMS
     * model uses. `MorphMany` rather than `MorphOne` so the relation is
     * identical in shape to the rest of the CMS; the resource takes `first()`.
     */
    public function images(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order');
    }
}
