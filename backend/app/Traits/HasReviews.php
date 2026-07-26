<?php

namespace App\Traits;

use App\Models\Review;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives a content model guest reviews plus the denormalized aggregates the
 * mobile list screens read. `rating_avg`/`rating_count` are written only by
 * RecalculateRatingAction — never mass-assigned.
 */
trait HasReviews
{
    public function initializeHasReviews(): void
    {
        $this->mergeCasts([
            'rating_avg'   => 'decimal:1',
            'rating_count' => 'integer',
        ]);
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function publishedReviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable')
            ->where('is_published', true)
            ->latest();
    }
}
