<?php

namespace Database\Seeders;

use App\Actions\Review\RecalculateRatingAction;
use App\Models\DiningVenue;
use App\Models\Guest;
use App\Models\Review;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Gives every room type and dining venue a non-null rating so the mobile list
 * screens have stars to render in a freshly seeded environment.
 */
class ReviewSeeder extends Seeder
{
    private const COMMENTS = [
        'Spotless, quiet, and the staff could not have been more helpful.',
        'Great location and a very comfortable stay. Would come back.',
        'Good value overall — breakfast was the highlight.',
        'Pleasant enough, though the air conditioning was noisy at night.',
    ];

    public function run(RecalculateRatingAction $recalculate): void
    {
        $guests = Guest::query()->take(4)->get();

        if ($guests->isEmpty()) {
            return;
        }

        $this->review(RoomType::where('is_active', true)->get(), $guests, $recalculate);
        $this->review(DiningVenue::where('is_active', true)->get(), $guests, $recalculate);
    }

    private function review(Collection $subjects, Collection $guests, RecalculateRatingAction $recalculate): void
    {
        foreach ($subjects as $subject) {
            foreach ($guests as $i => $guest) {
                Review::updateOrCreate(
                    [
                        'guest_id'        => $guest->id,
                        'reviewable_type' => $subject->getMorphClass(),
                        'reviewable_id'   => $subject->getKey(),
                    ],
                    [
                        // 5,4,5,3 → a 4.3 average, so the UI exercises a decimal.
                        'rating'           => [5, 4, 5, 3][$i % 4],
                        'comment'          => self::COMMENTS[$i % count(self::COMMENTS)],
                        'is_verified_stay' => $i % 2 === 0,
                        'is_published'     => true,
                    ],
                );
            }

            $recalculate->handle($subject);
        }
    }
}
