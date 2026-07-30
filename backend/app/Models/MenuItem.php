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

class MenuItem extends Model
{
    use HasFactory, HasUuid, HasTranslations, LogsActivity, PurgesMedia;

    protected $translatable = ['name', 'description'];

    protected $fillable = ['menu_category_id', 'name', 'description', 'price_usd', 'is_vegan', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'is_vegan' => 'boolean', 'price_usd' => 'decimal:2'];

    public function category(): BelongsTo { return $this->belongsTo(MenuCategory::class, 'menu_category_id'); }

    /** Dish photo — one image, surfaced as `photo` by the resource. */
    public function images(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order');
    }
}
