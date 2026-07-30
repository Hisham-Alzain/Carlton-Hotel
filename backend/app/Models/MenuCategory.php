<?php

namespace App\Models;

use App\Traits\HasTranslations;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use App\Traits\PurgesMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuCategory extends Model
{
    use HasFactory, HasUuid, HasTranslations, LogsActivity, PurgesMedia;

    protected $translatable = ['name'];

    protected $fillable = ['dining_venue_id', 'slug', 'name', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function venue(): BelongsTo { return $this->belongsTo(DiningVenue::class, 'dining_venue_id'); }
    public function items(): HasMany   { return $this->hasMany(MenuItem::class); }

    /**
     * A category carries no media of its own; its dishes do, and
     * `menu_items.menu_category_id` is `ON DELETE CASCADE`, so those rows are
     * only reachable from here.
     */
    protected function mediaCascades(): array
    {
        return ['items'];
    }
}
