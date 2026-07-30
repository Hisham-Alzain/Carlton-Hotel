<?php

namespace App\Models;

use App\Traits\HasTranslations;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use App\Traits\PurgesMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A concierge-arranged experience. See the migration for why this is neither a
 * `Facility` nor a `ServiceItem`.
 */
class Experience extends Model
{
    use HasFactory, HasUuid, HasTranslations, LogsActivity, PurgesMedia;

    protected $translatable = ['title', 'description'];

    protected $fillable = [
        'slug',
        'title',
        'description',
        'category',
        'duration_minutes',
        'price_usd',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'duration_minutes' => 'integer',
        'price_usd'        => 'decimal:2',
    ];

    /**
     * Experience photography, through the same `Media` morph every other CMS
     * model uses. `MorphMany` so an editor can carry a small gallery; the
     * resource surfaces the first one as `image` for the site's card grid.
     */
    public function images(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order');
    }
}
