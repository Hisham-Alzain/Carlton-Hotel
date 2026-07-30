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

    /**
     * `group_size` and `duration_label` are prose the site prints verbatim
     * ("2–6 guests", "Half day", "ساعتان – 3 ساعات") — see the migration for why
     * neither can be a number.
     */
    protected $translatable = ['title', 'description', 'group_size', 'duration_label'];

    protected $fillable = [
        'slug',
        'title',
        'description',
        'category',
        'group_size',
        // The schedulable upper bound, alongside the published range it belongs
        // to. Both are kept on purpose; the migration explains.
        'duration_minutes',
        'duration_label',
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
