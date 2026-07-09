<?php

namespace App\Models;

use App\Traits\HasTranslations;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class RoomType extends Model
{
    use HasFactory, HasUuid, HasTranslations, LogsActivity;

    protected $translatable = ['name', 'description'];

    protected $fillable = [
        'name',
        'description',
        'amenities',
        'base_occupancy',
        'max_occupancy',
        'size_sqm',
        'base_price_usd',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'amenities'      => 'array',
        'is_active'      => 'boolean',
        'base_price_usd' => 'decimal:2',
        'size_sqm'       => 'decimal:2',
    ];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function images(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order');
    }
}
