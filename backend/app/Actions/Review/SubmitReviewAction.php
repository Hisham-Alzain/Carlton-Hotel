<?php

namespace App\Actions\Review;

use App\Enums\ReservationStatus;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Review;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Create or replace a guest's review of a room type or dining venue.
 *
 * A guest holds at most one review per subject (enforced by a unique index), so
 * re-submitting edits the existing row rather than stacking duplicates.
 */
class SubmitReviewAction
{
    public function __construct(private readonly RecalculateRatingAction $recalculate) {}

    public function handle(Guest $guest, Model $reviewable, array $data): array
    {
        // "Verified stay" is derived server-side, never taken from the client.
        $stay = Reservation::where('guest_id', $guest->id)
            ->whereIn('status', [ReservationStatus::CHECKED_IN, ReservationStatus::CHECKED_OUT])
            ->orderByDesc('check_in')
            ->first();

        $review = DB::transaction(function () use ($guest, $reviewable, $data, $stay) {
            $review = Review::updateOrCreate(
                [
                    'guest_id'        => $guest->id,
                    'reviewable_type' => $reviewable->getMorphClass(),
                    'reviewable_id'   => $reviewable->getKey(),
                ],
                [
                    'rating'           => $data['rating'],
                    'comment'          => $data['comment'] ?? null,
                    'reservation_id'   => $stay?->id,
                    'is_verified_stay' => $stay !== null,
                ],
            );

            $this->recalculate->handle($reviewable);

            return $review;
        });

        $review->load('guest');

        return ['data' => $review, 'code' => $review->wasRecentlyCreated ? 201 : 200];
    }
}
