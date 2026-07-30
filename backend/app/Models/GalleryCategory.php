<?php

namespace App\Models;

use App\Traits\CascadesSoftDeletes;
use App\Traits\HasTranslations;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use App\Traits\PurgesMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A filter chip above the website's photo gallery. See the migration for why the
 * chips are rows rather than an enum.
 */
class GalleryCategory extends Model
{
    use HasFactory, HasUuid, HasTranslations, LogsActivity, PurgesMedia, SoftDeletes, CascadesSoftDeletes;

    protected $translatable = ['name'];

    protected $fillable = [
        'slug',
        'name',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(GalleryItem::class)->orderBy('sort_order');
    }

    /**
     * `gallery_items.gallery_category_id` is `ON DELETE CASCADE` — the migration
     * already promised these would be "cleaned up by the same morph-delete path",
     * which is this. A soft delete has to carry down too: `GalleryItemService`'s
     * public read joins `gallery_categories` for ordering, so a marked chip would
     * still publish its photographs.
     */
    protected function softDeleteCascades(): array
    {
        return ['items'];
    }
}
