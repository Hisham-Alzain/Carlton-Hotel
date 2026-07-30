<?php

namespace App\Models;

use App\Traits\HasTranslations;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use App\Traits\PurgesMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One photograph in the website's gallery. Deleted with its category — the FK
 * cascades; see the migration for why.
 */
class GalleryItem extends Model
{
    use HasFactory, HasUuid, HasTranslations, LogsActivity, PurgesMedia, SoftDeletes;

    protected $translatable = ['caption'];

    protected $fillable = [
        'gallery_category_id',
        'caption',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(GalleryCategory::class, 'gallery_category_id');
    }

    /**
     * The photograph itself. `MorphMany` like every other CMS model so the media
     * routes and the `mediable` morph stay one code path; a gallery item carries
     * exactly one image and the resource surfaces it as `image`.
     */
    public function images(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order');
    }
}
