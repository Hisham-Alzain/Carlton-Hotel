<?php

namespace App\Models;

use App\Traits\CascadesSoftDeletes;
use App\Traits\HasReviews;
use App\Traits\HasTranslations;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use App\Traits\PurgesMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiningVenue extends Model
{
    use HasFactory, HasUuid, HasTranslations, HasReviews, LogsActivity, PurgesMedia, SoftDeletes, CascadesSoftDeletes;

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

    /** Gallery images only — never the menu file (Phase 8, D-07). */
    public function images(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')
            ->where('collection', 'images')
            ->orderBy('sort_order');
    }

    /**
     * The downloadable menu file (Phase 8, D-07): a `collection = menu` media
     * row, latest wins. Managed only via `/cms/dining-venues/{v}/menu-file`.
     */
    public function menuFile(): MorphOne
    {
        // Constraint inside ofMany(): an outer where() would run after the
        // max(id) pick, so an image uploaded later would hide the menu.
        return $this->morphOne(Media::class, 'mediable')
            ->ofMany(['id' => 'max'], fn ($query) => $query->where('collection', 'menu'));
    }

    /**
     * The venue's menu. Declared here — the inverse of `MenuCategory::venue()` —
     * because the delete cascade needs a relation to walk: a venue reaches its
     * categories and, through them, every dish.
     */
    public function menuCategories(): HasMany
    {
        return $this->hasMany(MenuCategory::class);
    }

    /**
     * `menu_categories.dining_venue_id` is `ON DELETE CASCADE`, and a soft delete
     * never fires it. This is the worst case in the CMS: the public menu is read
     * through `MenuItemService::menuForVenue()`, which joins `menu_categories`
     * rather than asking the venue, so a merely-marked venue would keep serving
     * its full menu. Carrying the delete down two levels is what stops that.
     */
    protected function softDeleteCascades(): array
    {
        return ['menuCategories'];
    }
}
