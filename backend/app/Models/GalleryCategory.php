<?php

namespace App\Models;

use App\Traits\HasTranslations;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use App\Traits\PurgesMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A filter chip above the website's photo gallery. See the migration for why the
 * chips are rows rather than an enum.
 */
class GalleryCategory extends Model
{
    use HasFactory, HasUuid, HasTranslations, LogsActivity, PurgesMedia;

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
     * A chip carries no media of its own; its photographs do, and
     * `gallery_items.gallery_category_id` is `ON DELETE CASCADE` — the migration
     * already promised these would be "cleaned up by the same morph-delete
     * path", which is this.
     */
    protected function mediaCascades(): array
    {
        return ['items'];
    }
}
