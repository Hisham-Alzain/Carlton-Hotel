<?php

namespace App\Models;

use App\Traits\HasTranslations;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use App\Traits\PurgesMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An editorial article in the website's Journal.
 *
 * `published_on` is a display date only — see the migration. There is no
 * "published" scope on this model on purpose: the moment one exists, someone
 * will reach for it and reintroduce the scheduling behaviour the product
 * decision rejected. Visibility is `is_active`, applied in
 * `JournalPostService::indexPublic()` and the public controller's `show()`.
 */
class JournalPost extends Model
{
    use HasFactory, HasUuid, HasTranslations, LogsActivity, PurgesMedia, SoftDeletes;

    protected $translatable = ['title', 'excerpt', 'body', 'category'];

    protected $fillable = [
        'slug',
        'title',
        'excerpt',
        'body',
        'category',
        'published_on',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'published_on' => 'date',
        'is_active'    => 'boolean',
    ];

    /**
     * Cover image (and any inline figures), through the same `Media` morph every
     * other CMS model uses. `MorphMany` rather than `MorphOne` keeps the
     * relation identical in shape to the rest of the CMS; the resource takes
     * `first()` as the cover.
     */
    public function images(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order');
    }
}
