<?php

namespace App\Models;

use App\Enums\RoomView;
use App\Traits\HasReviews;
use App\Traits\HasTranslations;
use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use App\Traits\PurgesMedia;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class RoomType extends Model
{
    use HasFactory, HasUuid, HasTranslations, HasReviews, LogsActivity, PurgesMedia;

    protected $translatable = ['name', 'description'];

    protected $fillable = [
        'name',
        'description',
        'amenities',
        'view_type',
        'bed_types',
        'base_occupancy',
        'max_occupancy',
        'size_sqm',
        'base_price_usd',
        'cancellation_hours',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        // Legacy free-text list, superseded by the `amenityList` relation. Kept
        // so the column stays writable until the dashboard drops it.
        'amenities'      => 'array',
        'view_type'      => RoomView::class,
        'bed_types'      => 'array',
        'is_active'      => 'boolean',
        'base_price_usd' => 'decimal:2',
        'size_sqm'       => 'decimal:2',
    ];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * `rooms.room_type_id` is `ON DELETE CASCADE`, so the database removes the
     * rooms without Eloquent ever seeing them — their photography has to be
     * purged from here, while they are still readable.
     */
    protected function mediaCascades(): array
    {
        return ['rooms'];
    }

    public function images(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order');
    }

    /**
     * Seeded amenity catalog for this room type.
     *
     * Named `amenityList` rather than `amenities` because the legacy JSON column
     * of the same name still occupies that attribute.
     */
    public function amenityList(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class)
            ->withPivot(['is_highlight', 'sort_order'])
            ->orderBy('amenity_room_type.sort_order');
    }

    /**
     * The four amenities shown on the room card. Prefers the explicitly flagged
     * highlights and tops up from the head of the list when fewer than four are
     * flagged. Reads the loaded relation only — never queries.
     */
    public function highlightAmenities(int $limit = 4): Collection
    {
        $all = $this->amenityList;

        $highlights = $all->where('pivot.is_highlight', true)->take($limit);

        if ($highlights->count() < $limit) {
            $highlights = $highlights->concat(
                $all->whereNotIn('id', $highlights->pluck('id'))->take($limit - $highlights->count()),
            );
        }

        return $highlights->values();
    }
}
