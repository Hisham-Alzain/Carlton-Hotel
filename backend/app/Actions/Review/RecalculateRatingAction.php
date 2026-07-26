<?php

namespace App\Actions\Review;

use App\Models\Review;
use Illuminate\Database\Eloquent\Model;

/**
 * Single writer for the denormalized rating columns.
 *
 * Callers run this inside the transaction that changed the underlying reviews,
 * so the aggregate can never drift from the rows it summarizes.
 */
class RecalculateRatingAction
{
    public function handle(Model $reviewable): array
    {
        $stats = Review::query()
            ->where('reviewable_type', $reviewable->getMorphClass())
            ->where('reviewable_id', $reviewable->getKey())
            ->where('is_published', true)
            ->selectRaw('COUNT(*) as total, AVG(rating) as average')
            ->first();

        $count = (int) ($stats->total ?? 0);

        $reviewable->forceFill([
            'rating_avg'   => $count > 0 ? round((float) $stats->average, 1) : null,
            'rating_count' => $count,
        ])->save();

        return ['data' => $reviewable, 'code' => 200];
    }
}
