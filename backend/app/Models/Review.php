<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Review extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    protected $fillable = [
        'guest_id',
        'reviewable_type',
        'reviewable_id',
        'reservation_id',
        'rating',
        'comment',
        'is_verified_stay',
        'is_published',
    ];

    protected $casts = [
        'rating'           => 'integer',
        'is_verified_stay' => 'boolean',
        'is_published'     => 'boolean',
    ];

    public function guest(): BelongsTo       { return $this->belongsTo(Guest::class); }
    public function reservation(): BelongsTo { return $this->belongsTo(Reservation::class); }
    public function reviewable(): MorphTo    { return $this->morphTo(); }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
