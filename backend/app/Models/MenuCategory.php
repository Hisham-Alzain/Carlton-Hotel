<?php

namespace App\Models;

use App\Traits\CascadesSoftDeletes;
use App\Traits\HasTranslations;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use App\Traits\PurgesMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuCategory extends Model
{
    use HasFactory, HasUuid, HasTranslations, LogsActivity, PurgesMedia, SoftDeletes, CascadesSoftDeletes;

    protected $translatable = ['name'];

    protected $fillable = ['dining_venue_id', 'slug', 'name', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function venue(): BelongsTo { return $this->belongsTo(DiningVenue::class, 'dining_venue_id'); }
    public function items(): HasMany   { return $this->hasMany(MenuItem::class); }

    /**
     * `menu_items.menu_category_id` is `ON DELETE CASCADE`. The dishes are only
     * reachable from here, so this is the second leg of the venue → category →
     * dish cascade as well as the whole of a standalone category delete.
     */
    protected function softDeleteCascades(): array
    {
        return ['items'];
    }
}
