<?php

namespace App\Actions\Review;

use App\Models\Review;
use Illuminate\Support\Facades\DB;

/**
 * Staff moderation toggle. Unpublishing hides guest-authored text from the
 * public endpoints and removes it from the rating aggregate in the same write.
 */
class SetReviewPublishedAction
{
    public function __construct(private readonly RecalculateRatingAction $recalculate) {}

    public function handle(Review $review, bool $isPublished): array
    {
        DB::transaction(function () use ($review, $isPublished) {
            $review->update(['is_published' => $isPublished]);
            $this->recalculate->handle($review->reviewable);
        });

        $review->refresh()->load('guest');

        return ['data' => $review, 'code' => 200];
    }
}
