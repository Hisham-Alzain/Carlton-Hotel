<?php

namespace App\Models;

use App\Traits\HasReviews;
use App\Traits\HasTranslations;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use App\Traits\PurgesMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DiningVenue extends Model
{
    use HasFactory, HasUuid, HasTranslations, HasReviews, LogsActivity, PurgesMedia;

    protected $translatable = ['name', 'description', 'cuisine_type', 'location', 'hours'];

    protected $fillable = [
        'name',
        'description',
        'cuisine_type',
        'location',
        'hours',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function images(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order');
    }

    /**
     * The venue's menu. Declared here — the inverse of `MenuCategory::venue()` —
     * because `PurgesMedia` needs a relation to walk: deleting a venue cascades
     * to its categories and, through them, to every dish, and dish photography
     * would otherwise be stranded.
     */
    public function menuCategories(): HasMany
    {
        return $this->hasMany(MenuCategory::class);
    }

    /** `menu_categories.dining_venue_id` is `ON DELETE CASCADE`. */
    protected function mediaCascades(): array
    {
        return ['menuCategories'];
    }
}
